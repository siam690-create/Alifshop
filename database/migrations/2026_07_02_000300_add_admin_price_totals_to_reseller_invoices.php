<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reseller_invoices') && !Schema::hasColumn('reseller_invoices', 'total_admin_price')) {
            Schema::table('reseller_invoices', function (Blueprint $table) {
                $table->decimal('total_admin_price', 15, 2)->default(0)->after('total_collected');
            });
        }

        if (Schema::hasTable('reseller_invoice_items') && !Schema::hasColumn('reseller_invoice_items', 'admin_price_total')) {
            Schema::table('reseller_invoice_items', function (Blueprint $table) {
                $table->decimal('admin_price_total', 15, 2)->default(0)->after('collected_amount');
            });
        }

        $this->backfillTotals();
    }

    public function down(): void
    {
        if (Schema::hasTable('reseller_invoice_items') && Schema::hasColumn('reseller_invoice_items', 'admin_price_total')) {
            Schema::table('reseller_invoice_items', function (Blueprint $table) {
                $table->dropColumn('admin_price_total');
            });
        }

        if (Schema::hasTable('reseller_invoices') && Schema::hasColumn('reseller_invoices', 'total_admin_price')) {
            Schema::table('reseller_invoices', function (Blueprint $table) {
                $table->dropColumn('total_admin_price');
            });
        }
    }

    private function backfillTotals(): void
    {
        if (!Schema::hasTable('reseller_invoices') || !Schema::hasTable('reseller_invoice_items')) {
            return;
        }

        DB::table('reseller_invoice_items')
            ->select('reseller_invoice_items.id', 'reseller_invoice_items.order_id', 'reseller_invoice_items.invoice_type', 'reseller_invoice_items.collected_amount', 'reseller_invoice_items.delivery_fee')
            ->orderBy('reseller_invoice_items.id')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $invoiceType = strtolower((string) $item->invoice_type);
                    $isPaidReturn = in_array($invoiceType, ['paid_return', 'paid-return', 'paid return'], true);
                    $isReturn = !$isPaidReturn && in_array($invoiceType, ['return', 'returned'], true);
                    $adminPriceTotal = ($isReturn || $isPaidReturn)
                        ? 0
                        : (float) DB::table('order_details')
                            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
                            ->where('order_details.order_id', $item->order_id ?? null)
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

        if (Schema::hasColumn('products', 'reseller_price')) {
            $priceColumns[] = 'products.reseller_price';
        }

        if (Schema::hasColumn('products', 'wholesale_price')) {
            $priceColumns[] = 'products.wholesale_price';
        }

        if (Schema::hasColumn('order_details', 'reseller_price')) {
            $priceColumns[] = 'order_details.reseller_price';
        }

        if (Schema::hasColumn('order_details', 'admin_price')) {
            $priceColumns[] = 'order_details.admin_price';
        }

        if (Schema::hasColumn('order_details', 'product_admin_price')) {
            $priceColumns[] = 'order_details.product_admin_price';
        }

        if (Schema::hasColumn('order_details', 'purchase_price')) {
            $priceColumns[] = 'order_details.purchase_price';
        }

        if (Schema::hasColumn('products', 'purchase_price')) {
            $priceColumns[] = 'products.purchase_price';
        }

        if (Schema::hasColumn('products', 'buy_price')) {
            $priceColumns[] = 'products.buy_price';
        }

        if (Schema::hasColumn('products', 'admin_price')) {
            $priceColumns[] = 'products.admin_price';
        }

        $normalizedPriceColumns = array_map(fn ($column) => "NULLIF({$column}, 0)", $priceColumns);

        $unitPriceSql = $normalizedPriceColumns
            ? 'COALESCE(' . implode(', ', $normalizedPriceColumns) . ', 0)'
            : '0';

        return "COALESCE(SUM({$unitPriceSql} * GREATEST(COALESCE(order_details.qty, 1), 1)), 0) as total";
    }
};
