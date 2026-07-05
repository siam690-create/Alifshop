<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'paid_return_amount')) {
            $after = Schema::hasColumn('orders', 'shipping_charge') ? 'shipping_charge' : 'order_status';

            Schema::table('orders', function (Blueprint $table) use ($after) {
                $table->decimal('paid_return_amount', 15, 2)->default(0)->after($after);
            });
        }

        if (Schema::hasTable('order_statuses')) {
            $now = now();
            $payload = [
                'name' => 'Paid Return',
                'slug' => 'paid-return',
            ];

            if (Schema::hasColumn('order_statuses', 'status')) {
                $payload['status'] = 1;
            }

            if (Schema::hasColumn('order_statuses', 'created_at')) {
                $payload['created_at'] = $now;
            }

            if (Schema::hasColumn('order_statuses', 'updated_at')) {
                $payload['updated_at'] = $now;
            }

            DB::table('order_statuses')->updateOrInsert(['slug' => 'paid-return'], $payload);
        }

        $this->backfillResellerInvoiceTotals();

        Cache::forget('order_statuses_list');
        Cache::forget('order_status_list');
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'paid_return_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('paid_return_amount');
            });
        }

        if (Schema::hasTable('order_statuses')) {
            DB::table('order_statuses')->where('slug', 'paid-return')->delete();
        }

        Cache::forget('order_statuses_list');
        Cache::forget('order_status_list');
    }

    private function backfillResellerInvoiceTotals(): void
    {
        if (
            !Schema::hasTable('reseller_invoices')
            || !Schema::hasTable('reseller_invoice_items')
            || !Schema::hasTable('order_details')
            || !Schema::hasTable('products')
            || !Schema::hasColumn('reseller_invoice_items', 'admin_price_total')
        ) {
            return;
        }

        DB::table('reseller_invoice_items')
            ->select('id', 'order_id', 'invoice_type', 'collected_amount', 'delivery_fee')
            ->orderBy('id')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $invoiceType = strtolower((string) $item->invoice_type);
                    $isPaidReturn = in_array($invoiceType, ['paid_return', 'paid-return', 'paid return'], true);
                    $isReturn = !$isPaidReturn && in_array($invoiceType, ['return', 'returned'], true);

                    $adminPriceTotal = ($isReturn || $isPaidReturn)
                        ? 0
                        : (float) DB::table('order_details')
                            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
                            ->where('order_details.order_id', $item->order_id)
                            ->selectRaw($this->adminPriceSelectSql())
                            ->value('total');

                    $deliveryFee = (float) $item->delivery_fee;
                    $payout = ($isReturn || $isPaidReturn)
                        ? ((float) $item->collected_amount - $deliveryFee)
                        : ((float) $item->collected_amount - $adminPriceTotal - $deliveryFee);

                    DB::table('reseller_invoice_items')
                        ->where('id', $item->id)
                        ->update([
                            'admin_price_total' => $adminPriceTotal,
                            'payout' => $payout,
                        ]);
                }
            });

        if (!Schema::hasColumn('reseller_invoices', 'total_admin_price')) {
            return;
        }

        DB::table('reseller_invoices')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $totals = DB::table('reseller_invoice_items')
                        ->where('reseller_invoice_id', $invoice->id)
                        ->selectRaw('
                            COUNT(*) as orders_count,
                            COALESCE(SUM(collected_amount), 0) as total_collected,
                            COALESCE(SUM(admin_price_total), 0) as total_admin_price,
                            COALESCE(SUM(delivery_fee), 0) as total_delivery_fee,
                            COALESCE(SUM(cod_fee), 0) as total_cod_fee,
                            COALESCE(SUM(discount), 0) as total_discount,
                            COALESCE(SUM(additional_charge), 0) as total_additional_charge,
                            COALESCE(SUM(compensation), 0) as total_compensation,
                            COALESCE(SUM(promo_discount), 0) as total_promo_discount,
                            COALESCE(SUM(payout), 0) as net_payable
                        ')
                        ->first();

                    DB::table('reseller_invoices')
                        ->where('id', $invoice->id)
                        ->update((array) $totals);
                }
            });
    }

    private function adminPriceSelectSql(): string
    {
        $priceColumns = [];

        foreach ([
            ['products', 'reseller_price'],
            ['products', 'wholesale_price'],
            ['order_details', 'reseller_price'],
            ['order_details', 'admin_price'],
            ['order_details', 'product_admin_price'],
            ['order_details', 'purchase_price'],
            ['products', 'purchase_price'],
            ['products', 'buy_price'],
            ['products', 'admin_price'],
        ] as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                $priceColumns[] = "{$table}.{$column}";
            }
        }

        $normalizedPriceColumns = array_map(fn ($column) => "NULLIF({$column}, 0)", $priceColumns);
        $unitPriceSql = $normalizedPriceColumns
            ? 'COALESCE(' . implode(', ', $normalizedPriceColumns) . ', 0)'
            : '0';

        return "COALESCE(SUM({$unitPriceSql} * GREATEST(COALESCE(order_details.qty, 1), 1)), 0) as total";
    }
};
