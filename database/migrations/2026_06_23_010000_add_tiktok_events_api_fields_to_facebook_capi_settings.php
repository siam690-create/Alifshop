<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_capi_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('facebook_capi_settings', 'tiktok_pixel_id')) {
                $table->string('tiktok_pixel_id')->nullable()->after('domain_verification_token');
            }

            if (!Schema::hasColumn('facebook_capi_settings', 'tiktok_access_token')) {
                $table->text('tiktok_access_token')->nullable()->after('tiktok_pixel_id');
            }

            if (!Schema::hasColumn('facebook_capi_settings', 'tiktok_test_event_code')) {
                $table->string('tiktok_test_event_code')->nullable()->after('tiktok_access_token');
            }

            if (!Schema::hasColumn('facebook_capi_settings', 'tiktok_status')) {
                $table->boolean('tiktok_status')->default(false)->after('tiktok_test_event_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facebook_capi_settings', function (Blueprint $table) {
            foreach (['tiktok_status', 'tiktok_test_event_code', 'tiktok_access_token', 'tiktok_pixel_id'] as $column) {
                if (Schema::hasColumn('facebook_capi_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
