<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reseller_invoices')) {
            Schema::create('reseller_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no')->unique();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('cycle', 20)->default('daily');
                $table->timestamp('period_started_at')->nullable();
                $table->timestamp('period_ended_at')->nullable();
                $table->unsignedInteger('orders_count')->default(0);
                $table->decimal('total_collected', 15, 2)->default(0);
                $table->decimal('total_delivery_fee', 15, 2)->default(0);
                $table->decimal('total_cod_fee', 15, 2)->default(0);
                $table->decimal('total_discount', 15, 2)->default(0);
                $table->decimal('total_additional_charge', 15, 2)->default(0);
                $table->decimal('total_compensation', 15, 2)->default(0);
                $table->decimal('total_promo_discount', 15, 2)->default(0);
                $table->decimal('net_payable', 15, 2)->default(0);
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_invoices');
    }
};
