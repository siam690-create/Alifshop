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
        if (!Schema::hasColumn('orders', 'order_source_channel')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('order_source_channel', 50)->nullable()->after('note');
            });
        }

        DB::table('order_statuses')
            ->where(function ($query) {
                $query->where('slug', 'processing')
                    ->orWhereRaw('LOWER(`name`) = ?', ['processing']);
            })
            ->update(['name' => 'Approved']);

        Cache::forget('order_status_list');
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'order_source_channel')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('order_source_channel');
            });
        }

        DB::table('order_statuses')
            ->where('slug', 'processing')
            ->whereRaw('LOWER(`name`) = ?', ['approved'])
            ->update(['name' => 'Processing']);

        Cache::forget('order_status_list');
    }
};
