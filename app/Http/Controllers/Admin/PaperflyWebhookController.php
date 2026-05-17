<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundTransaction;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderStatus;
use App\Models\SmsGateway;
use App\Models\User;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaperflyWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('Paperfly Webhook Received', [
                'payload' => $payload,
                'headers' => $request->headers->all(),
            ]);

            $eventName = strtolower(trim((string) $request->input('event')));
            $data = (array) $request->input('data', []);

            if ($eventName === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required field: event',
                ], 400);
            }

            $paperflyOrderNumber = trim((string) ($data['order_number'] ?? $data['orderNumber'] ?? ''));
            $merchantReference = trim((string) ($data['merchant_order_reference'] ?? $data['merchantOrderReference'] ?? ''));

            $order = $this->findOrder($merchantReference, $paperflyOrderNumber);

            if (!$order) {
                Log::warning('Paperfly Webhook: Order not found', [
                    'event' => $eventName,
                    'merchant_order_reference' => $merchantReference,
                    'order_number' => $paperflyOrderNumber,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $this->syncTrackingFields($order, $paperflyOrderNumber);

            $newOrderStatus = $this->mapWebhookToOrderStatus($eventName, $data);

            if ($newOrderStatus === null) {
                Log::info('Paperfly Webhook: No status mapping applied', [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'event' => $eventName,
                    'data' => $data,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Webhook received, no status change required',
                ], 200);
            }

            $oldStatus = (int) $order->order_status;
            $newOrderStatus = (int) $newOrderStatus;

            if ($oldStatus !== $newOrderStatus) {
                $order->order_status = $newOrderStatus;
                $order->save();

                $this->handleStockChange($order, $oldStatus, $newOrderStatus);

                $deliveredStatusId = $this->resolveStatusId(['delivered', 'completed', 'complete'], 6);
                if ($newOrderStatus === (int) $deliveredStatusId && $oldStatus !== (int) $deliveredStatusId) {
                    FundTransaction::create([
                        'direction' => 'in',
                        'source' => 'sale',
                        'source_id' => $order->id,
                        'amount' => $order->amount,
                        'note' => 'Order complete via Paperfly webhook (#' . $order->invoice_id . ')',
                        'created_by' => 1,
                    ]);

                    $this->distributeVendorEarnings($order);
                    $this->creditResellerWallet($order);
                }

                $this->sendStatusUpdateSMS($order, $newOrderStatus);
            }

            Log::info('Paperfly Webhook: Order processed successfully', [
                'order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
                'event' => $eventName,
                'old_status' => $oldStatus,
                'new_status' => $newOrderStatus,
                'paperfly_order_number' => $paperflyOrderNumber,
                'merchant_order_reference' => $merchantReference,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Paperfly Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    private function findOrder(string $merchantReference, string $paperflyOrderNumber): ?Order
    {
        $candidates = array_values(array_unique(array_filter([
            $merchantReference,
            $paperflyOrderNumber,
        ], fn ($value) => trim((string) $value) !== '')));

        if (empty($candidates)) {
            return null;
        }

        return Order::query()
            ->where(function ($query) use ($merchantReference, $paperflyOrderNumber, $candidates) {
                if ($merchantReference !== '') {
                    $query->orWhere('invoice_id', $merchantReference);
                }

                if ($paperflyOrderNumber !== '') {
                    $query->orWhere('courier_tracking_id', $paperflyOrderNumber)
                        ->orWhere('consignment_id', $paperflyOrderNumber);
                }

                $query->orWhereIn('invoice_id', $candidates)
                    ->orWhereIn('courier_tracking_id', $candidates)
                    ->orWhereIn('consignment_id', $candidates);
            })
            ->first();
    }

    private function syncTrackingFields(Order $order, string $paperflyOrderNumber): void
    {
        if ($paperflyOrderNumber === '') {
            return;
        }

        $dirty = false;

        if (empty($order->courier_type)) {
            $order->courier_type = 'paperfly';
            $dirty = true;
        }

        if ((string) $order->courier_tracking_id !== $paperflyOrderNumber) {
            $order->courier_tracking_id = $paperflyOrderNumber;
            $dirty = true;
        }

        if ((string) $order->consignment_id !== $paperflyOrderNumber) {
            $order->consignment_id = $paperflyOrderNumber;
            $dirty = true;
        }

        if (empty($order->courier_sent_at)) {
            $order->courier_sent_at = now();
            $dirty = true;
        }

        if ($dirty) {
            $order->save();
        }
    }

    private function mapWebhookToOrderStatus(string $eventName, array $data): ?int
    {
        $eventName = strtolower(trim($eventName));
        $statusText = strtolower(trim((string) ($data['order_status'] ?? $data['status'] ?? '')));

        $inCourierStatusId = $this->resolveStatusId(['in-courier', 'in_courier', 'in courier'], 5);
        $onTheWayStatusId = $this->resolveStatusId(['on-the-way', 'on_the_way', 'on the way'], $inCourierStatusId);
        $processingStatusId = $this->resolveStatusId(['processing'], 2);

        $eventMap = [
            'parcel.created' => $inCourierStatusId,
            'parcel.invoiced' => $inCourierStatusId,
            'parcel.picked_up' => $inCourierStatusId,
            'parcel.in_transit' => $inCourierStatusId,
            'parcel.received_at_point' => $inCourierStatusId,
            'parcel.assigned_for_delivery' => $onTheWayStatusId,
            'parcel.delivered' => $this->resolveStatusId(['delivered', 'completed', 'complete'], 6),
            'parcel.partial' => $this->resolveStatusId(['partial-delivered', 'partial_delivered', 'partial delivered']),
            'parcel.exchange' => $this->resolveStatusId(['delivered', 'completed', 'complete'], 6),
            'parcel.on_hold' => $processingStatusId,
            'parcel.return' => $this->resolveStatusId(['returned', 'return', 'return_to_merchant']),
            'parcel.return_transit' => $this->resolveStatusId(['returned', 'return', 'return_to_merchant']),
            'parcel.return_to_merchant' => $this->resolveStatusId(['returned', 'return', 'return_to_merchant']),
            'parcel.cancelled' => $this->resolveStatusId(['cancelled', 'canceled', 'cancel'], 11),
        ];

        if (isset($eventMap[$eventName]) && $eventMap[$eventName] !== null) {
            return (int) $eventMap[$eventName];
        }

        if ($statusText !== '') {
            $normalized = str_replace(['_', '-'], ' ', $statusText);

            $statusGroups = [
                [
                    'match' => ['delivered', 'completed', 'complete', 'exchange'],
                    'target' => ['delivered', 'completed', 'complete'],
                    'fallback' => 6,
                ],
                [
                    'match' => ['partial', 'partial delivered', 'partially delivered'],
                    'target' => ['partial-delivered', 'partial_delivered', 'partial delivered'],
                    'fallback' => null,
                ],
                [
                    'match' => ['return', 'returned', 'return to merchant', 'return_to_merchant', 'return transit'],
                    'target' => ['returned', 'return', 'return_to_merchant'],
                    'fallback' => null,
                ],
                [
                    'match' => ['cancelled', 'canceled', 'cancel'],
                    'target' => ['cancelled', 'canceled', 'cancel'],
                    'fallback' => 11,
                ],
                [
                    'match' => ['on hold'],
                    'target' => ['processing'],
                    'fallback' => 2,
                ],
                [
                    'match' => ['point received', 'received at point', 'in transit', 'picked up'],
                    'target' => ['in-courier', 'in_courier', 'in courier'],
                    'fallback' => 5,
                ],
            ];

            foreach ($statusGroups as $group) {
                if (in_array($normalized, $group['match'], true)) {
                    return $this->resolveStatusId($group['target'], $group['fallback']);
                }
            }
        }

        return null;
    }

    private function handleStockChange(Order $order, int $oldStatus, int $newStatus): void
    {
        $activeStatuses = array_values(array_unique(array_filter([
            $this->resolveStatusId(['pending'], 1),
            $this->resolveStatusId(['processing'], 2),
            $this->resolveStatusId(['on-the-way', 'on_the_way', 'on the way'], 3),
            $this->resolveStatusId(['in-courier', 'in_courier', 'in courier'], 5),
            $this->resolveStatusId(['delivered', 'completed', 'complete'], 6),
            $this->resolveStatusId(['partial-delivered', 'partial_delivered', 'partial delivered']),
        ])));

        if (in_array($newStatus, $activeStatuses, true) && !in_array($oldStatus, $activeStatuses, true)) {
            $details = OrderDetails::where('order_id', $order->id)
                ->with('product:id,stock')
                ->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = max(0, $row->product->stock - $row->qty);
                    $row->product->save();
                }
            }
        }

        $cancelLikeStatuses = array_values(array_unique(array_filter([
            $this->resolveStatusId(['cancelled', 'canceled', 'cancel'], 11),
            $this->resolveStatusId(['returned', 'return', 'return_to_merchant']),
        ])));

        if (in_array($newStatus, $cancelLikeStatuses, true) && in_array($oldStatus, $activeStatuses, true)) {
            $details = OrderDetails::where('order_id', $order->id)
                ->with('product:id,stock')
                ->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = $row->product->stock + $row->qty;
                    $row->product->save();
                }
            }
        }
    }

    private function distributeVendorEarnings(Order $order): void
    {
        $details = $order->orderdetails()
            ->with([
                'product:id,vendor_id,name',
                'product.vendor:id,commission_rate',
            ])
            ->get();

        foreach ($details as $item) {
            $product = $item->product;
            if (!$product || !$product->vendor_id || $item->vendor_paid_at) {
                continue;
            }

            $vendor = $product->vendor;
            if (!$vendor) {
                Log::warning('Paperfly Webhook: Vendor missing for product', ['product_id' => $product->id]);
                continue;
            }

            $commissionRate = $vendor->commission_rate ?? config('app.vendor_commission', 10);
            $lineTotal = (float) ($item->sale_price ?? 0) * (float) ($item->qty ?? 0);
            $adminCommission = round($lineTotal * ($commissionRate / 100), 2);
            $vendorEarning = max(0, round($lineTotal - $adminCommission, 2));

            $item->update([
                'vendor_id' => $product->vendor_id,
                'commission_rate' => $commissionRate,
                'admin_commission' => $adminCommission,
                'vendor_earning' => $vendorEarning,
                'vendor_paid_at' => now(),
            ]);

            $wallet = VendorWallet::firstOrCreate(['vendor_id' => $product->vendor_id]);
            $wallet->balance += $vendorEarning;
            $wallet->total_earned += $vendorEarning;
            $wallet->save();

            VendorWalletTransaction::create([
                'vendor_id' => $product->vendor_id,
                'type' => 'earning',
                'status' => 'completed',
                'amount' => $vendorEarning,
                'source_type' => 'order',
                'source_id' => $item->id,
                'note' => 'Order #' . $order->invoice_id . ' item earning (Paperfly)',
            ]);

            if ($adminCommission > 0) {
                FundTransaction::create([
                    'direction' => 'in',
                    'source' => 'vendor_commission',
                    'source_id' => $order->id,
                    'amount' => $adminCommission,
                    'note' => 'Vendor commission from Order #' . $order->invoice_id . ' - Product: ' . $item->product_name . ' (Paperfly)',
                    'created_by' => 1,
                ]);
            }
        }
    }

    private function creditResellerWallet(Order $order): void
    {
        if (!$order->reseller_profit || $order->reseller_profit <= 0 || $order->reseller_wallet_credited) {
            return;
        }

        $resellerUser = null;

        if ($order->user_id) {
            $candidate = User::find($order->user_id);
            if ($candidate && ($candidate->hasRole('reseller') || (isset($candidate->role) && strtolower((string) $candidate->role) === 'reseller'))) {
                $resellerUser = $candidate;
            }
        }

        if (!$resellerUser && $order->customer && $order->customer->email) {
            $resellerUser = User::where('email', $order->customer->email)
                ->where(function ($query) {
                    $query->where('role', 'reseller')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'reseller');
                        });
                })
                ->first();
        }

        if (!$resellerUser) {
            return;
        }

        $resellerUser->wallet_balance = ($resellerUser->wallet_balance ?? 0) + $order->reseller_profit;
        $resellerUser->save();

        $order->reseller_wallet_credited = true;
        $order->save();
    }

    private function sendStatusUpdateSMS(Order $order, int $newStatus): void
    {
        try {
            $smsGateway = SmsGateway::where('status', 1)->first();
            $siteSetting = GeneralSetting::first();
            $orderStatus = OrderStatus::find($newStatus);

            if ($smsGateway && $order->customer && $orderStatus) {
                $data = [
                    'api_key' => $smsGateway->api_key,
                    'number' => $order->customer->phone,
                    'type' => 'text',
                    'senderid' => $smsGateway->serderid,
                    'message' => "Dear {$order->customer->name},\r\n"
                        . "Your order (Order ID: {$order->invoice_id}) status has been updated to: {$orderStatus->name} via Paperfly Courier.\r\n"
                        . 'Thank you for using ' . ($siteSetting->name ?? config('app.name', 'SellwayBD')) . '!',
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $smsGateway->url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (\Throwable $e) {
            Log::error('Paperfly Webhook SMS sending failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveStatusId(array $aliases, ?int $fallback = null): ?int
    {
        foreach ($aliases as $alias) {
            $normalizedAlias = strtolower(trim($alias));
            $status = OrderStatus::query()
                ->whereRaw('LOWER(slug) = ?', [$normalizedAlias])
                ->orWhereRaw('LOWER(name) = ?', [$normalizedAlias])
                ->first();

            if ($status) {
                return (int) $status->id;
            }
        }

        return $fallback;
    }
}
