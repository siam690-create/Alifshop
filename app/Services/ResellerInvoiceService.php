<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ResellerInvoice;
use App\Models\ResellerInvoiceItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResellerInvoiceService
{
    public function generateDueInvoices(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?: now();
        $created = 0;

        User::where('role', 'reseller')->chunkById(50, function ($resellers) use ($asOf, &$created) {
            foreach ($resellers as $reseller) {
                if ($this->generateFor($reseller, $asOf, false)) {
                    $created++;
                }
            }
        });

        return $created;
    }

    public function generateFor(User $reseller, ?Carbon $asOf = null, bool $force = false): ?ResellerInvoice
    {
        $asOf = $asOf ?: now();
        $cycle = $reseller->reseller_payout_cycle ?: 'daily';
        $lastInvoice = ResellerInvoice::where('user_id', $reseller->id)->latest('period_ended_at')->first();

        if (!$force && !$this->cycleIsDue($cycle, $lastInvoice, $asOf)) {
            return null;
        }

        $orders = $this->eligibleOrders($reseller, $asOf);

        if ($orders->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($reseller, $orders, $cycle, $asOf) {
            $periodStart = optional($orders->min('created_at'))->copy();
            $periodEnd = optional($orders->max('created_at'))->copy() ?: $asOf;

            $invoice = ResellerInvoice::create([
                'invoice_no' => $this->makeInvoiceNo(),
                'user_id' => $reseller->id,
                'cycle' => $cycle,
                'period_started_at' => $periodStart,
                'period_ended_at' => $periodEnd,
                'status' => 'pending',
            ]);

            $totals = [
                'orders_count' => 0,
                'total_collected' => 0,
                'total_admin_price' => 0,
                'total_delivery_fee' => 0,
                'total_cod_fee' => 0,
                'total_discount' => 0,
                'total_additional_charge' => 0,
                'total_compensation' => 0,
                'total_promo_discount' => 0,
                'net_payable' => 0,
            ];

            foreach ($orders as $order) {
                $row = $this->makeItemPayload($invoice, $order, $reseller);
                ResellerInvoiceItem::create($row);

                $totals['orders_count']++;
                $totals['total_collected'] += $row['collected_amount'];
                $totals['total_admin_price'] += $row['admin_price_total'];
                $totals['total_delivery_fee'] += $row['delivery_fee'];
                $totals['total_cod_fee'] += $row['cod_fee'];
                $totals['total_discount'] += $row['discount'];
                $totals['total_additional_charge'] += $row['additional_charge'];
                $totals['total_compensation'] += $row['compensation'];
                $totals['total_promo_discount'] += $row['promo_discount'];
                $totals['net_payable'] += $row['payout'];
            }

            $invoice->update($totals);

            return $invoice->fresh(['items', 'user']);
        });
    }

    public function csvResponse(ResellerInvoice $invoice)
    {
        $invoice->loadMissing(['items', 'user']);
        $fileName = $invoice->invoice_no . '.csv';

        return response()->streamDownload(function () use ($invoice) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Consignment_ID',
                'Created_Date',
                'Invoice type',
                'Collected_Amount',
                'Admin_Price_Total',
                'Recipient_Name',
                'Recipient_Phone',
                'Collectable_Amount',
                'COD_fee',
                'Delivery_Fee',
                'Final_Fee',
                'Discount',
                'Additional_Charge',
                'Compensation',
                'Promo_Discount',
                'Payout',
                'Merchant_Order',
                'Store_name',
            ]);

            foreach ($invoice->items as $item) {
                fputcsv($handle, [
                    $item->consignment_id,
                    optional($item->order_date)->format('Y-m-d H:i:s'),
                    $item->invoice_type,
                    $item->collected_amount,
                    $item->admin_price_total,
                    $item->recipient_name,
                    $item->recipient_phone,
                    $item->collectable_amount,
                    $item->cod_fee,
                    $item->delivery_fee,
                    $item->final_fee,
                    $item->discount,
                    $item->additional_charge,
                    $item->compensation,
                    $item->promo_discount,
                    $item->payout,
                    $item->merchant_order,
                    $item->store_name,
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function eligibleOrders(User $reseller, Carbon $asOf): Collection
    {
        $hasResellerIdColumn = Schema::hasColumn('orders', 'reseller_id');

        return Order::with(['shipping', 'status', 'user', 'orderdetails.product'])
            ->where('created_at', '<=', $asOf)
            ->where(function ($query) use ($reseller, $hasResellerIdColumn) {
                $query->where('user_id', $reseller->id);
                if ($hasResellerIdColumn) {
                    $query->orWhere('reseller_id', $reseller->id);
                }
            })
            ->whereDoesntHave('resellerInvoiceItem')
            ->whereHas('status', function ($query) {
                $query->whereIn(DB::raw('LOWER(slug)'), ['delivered', 'completed', 'return', 'returned', 'paid-return', 'paid_return', 'partial-delivered'])
                    ->orWhereIn(DB::raw('LOWER(name)'), ['delivered', 'completed', 'return', 'returned', 'paid return', 'partial delivered']);
            })
            ->oldest('created_at')
            ->get();
    }

    private function makeItemPayload(ResellerInvoice $invoice, Order $order, User $reseller): array
    {
        $statusText = strtolower(trim(($order->status->slug ?? '') . ' ' . ($order->status->name ?? '')));
        $isPaidReturn = Str::contains($statusText, ['paid-return', 'paid_return', 'paid return', 'paid returned', 'return paid', 'returned paid']);
        $isReturned = !$isPaidReturn && Str::contains($statusText, 'return');
        $shipping = $order->shipping;
        $deliveryFee = (float) ($order->shipping_charge ?? 0);
        $paidReturnAmount = (float) ($order->paid_return_amount ?? 0);
        $collectedAmount = $isReturned
            ? 0
            : ($isPaidReturn ? $paidReturnAmount : (float) ($order->customer_payable_amount ?? $order->amount ?? 0));
        $adminPriceTotal = ($isReturned || $isPaidReturn) ? 0 : $this->adminPriceTotal($order);
        $payout = $isPaidReturn
            ? ($paidReturnAmount - $deliveryFee)
            : ($isReturned ? (-1 * $deliveryFee) : ($collectedAmount - $adminPriceTotal - $deliveryFee));

        return [
            'reseller_invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'invoice_type' => $isPaidReturn ? 'paid_return' : ($isReturned ? 'return' : 'delivery'),
            'consignment_id' => $order->courier_tracking_id ?? $order->tracking_id ?? $order->invoice_id ?? ('ORD-' . $order->id),
            'order_date' => $order->created_at,
            'collected_amount' => $collectedAmount,
            'admin_price_total' => $adminPriceTotal,
            'recipient_name' => $shipping->name ?? $order->customer_name ?? 'N/A',
            'recipient_phone' => $shipping->phone ?? $order->customer_phone ?? 'N/A',
            'collectable_amount' => $collectedAmount,
            'cod_fee' => 0,
            'delivery_fee' => $deliveryFee,
            'final_fee' => $deliveryFee,
            'discount' => (float) ($order->discount ?? 0),
            'additional_charge' => 0,
            'compensation' => 0,
            'promo_discount' => 0,
            'payout' => $payout,
            'merchant_order' => $order->invoice_id ?? (string) $order->id,
            'store_name' => $reseller->shop_name ?: $reseller->name,
            'status_snapshot' => trim(($order->status->name ?? '') ?: ($order->status->slug ?? '')),
        ];
    }

    private function adminPriceTotal(Order $order): float
    {
        $details = $order->relationLoaded('orderdetails')
            ? $order->orderdetails
            : $order->orderdetails()->with('product')->get();

        return (float) $details->sum(function ($detail) {
            $qty = max(1, (int) ($detail->qty ?? 1));
            $product = $detail->product;
            $unitAdminPrice = $this->firstPositivePrice([
                $detail->admin_price ?? null,
                $detail->product_admin_price ?? null,
                $detail->reseller_price ?? null,
                $product->reseller_price ?? null,
                $product->wholesale_price ?? null,
                $detail->purchase_price ?? null,
                $product->purchase_price ?? null,
                $product->buy_price ?? null,
                $product->admin_price ?? null,
            ]);

            return $unitAdminPrice * $qty;
        });
    }

    private function firstPositivePrice(array $prices): float
    {
        foreach ($prices as $price) {
            if ($price !== null && $price !== '' && is_numeric($price) && (float) $price > 0) {
                return (float) $price;
            }
        }

        return 0.0;
    }

    private function cycleIsDue(string $cycle, ?ResellerInvoice $lastInvoice, Carbon $asOf): bool
    {
        if (!$lastInvoice || !$lastInvoice->period_ended_at) {
            return true;
        }

        return match ($cycle) {
            'weekly' => $lastInvoice->period_ended_at->lt($asOf->copy()->startOfWeek()),
            'monthly' => $lastInvoice->period_ended_at->lt($asOf->copy()->startOfMonth()),
            default => $lastInvoice->period_ended_at->lt($asOf->copy()->startOfDay()),
        };
    }

    private function makeInvoiceNo(): string
    {
        do {
            $invoiceNo = 'RI' . now()->format('ymd') . strtoupper(Str::random(6));
        } while (ResellerInvoice::where('invoice_no', $invoiceNo)->exists());

        return $invoiceNo;
    }
}
