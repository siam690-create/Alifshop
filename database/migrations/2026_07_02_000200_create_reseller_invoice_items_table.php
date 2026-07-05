<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reseller_invoice_items')) {
            Schema::create('reseller_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reseller_invoice_id')->index();
                $table->unsignedInteger('order_id')->unique();
                $table->string('invoice_type')->default('delivery');
                $table->string('consignment_id')->nullable();
                $table->timestamp('order_date')->nullable();
                $table->decimal('collected_amount', 15, 2)->default(0);
                $table->string('recipient_name')->nullable();
                $table->string('recipient_phone')->nullable();
                $table->decimal('collectable_amount', 15, 2)->default(0);
                $table->decimal('cod_fee', 15, 2)->default(0);
                $table->decimal('delivery_fee', 15, 2)->default(0);
                $table->decimal('final_fee', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('additional_charge', 15, 2)->default(0);
                $table->decimal('compensation', 15, 2)->default(0);
                $table->decimal('promo_discount', 15, 2)->default(0);
                $table->decimal('payout', 15, 2)->default(0);
                $table->string('merchant_order')->nullable();
                $table->string('store_name')->nullable();
                $table->string('status_snapshot')->nullable();
                $table->timestamps();

                $table->foreign('reseller_invoice_id')->references('id')->on('reseller_invoices')->cascadeOnDelete();
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_invoice_items');
    }
};
