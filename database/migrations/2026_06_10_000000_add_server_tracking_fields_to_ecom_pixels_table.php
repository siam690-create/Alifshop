<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecom_pixels', function (Blueprint $table) {
            if (!Schema::hasColumn('ecom_pixels', 'access_token')) {
                $table->text('access_token')->nullable()->after('code');
            }

            if (!Schema::hasColumn('ecom_pixels', 'test_event_code')) {
                $table->string('test_event_code')->nullable()->after('access_token');
            }

            if (!Schema::hasColumn('ecom_pixels', 'browser_tracking_enabled')) {
                $table->boolean('browser_tracking_enabled')->default(true)->after('test_event_code');
            }

            if (!Schema::hasColumn('ecom_pixels', 'server_tracking_enabled')) {
                $table->boolean('server_tracking_enabled')->default(false)->after('browser_tracking_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecom_pixels', function (Blueprint $table) {
            foreach ([
                'server_tracking_enabled',
                'browser_tracking_enabled',
                'test_event_code',
                'access_token',
            ] as $column) {
                if (Schema::hasColumn('ecom_pixels', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
