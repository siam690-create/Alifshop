<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_capi_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('facebook_capi_settings', 'purchase_trigger')) {
                $table->string('purchase_trigger')->default('order_created')->after('test_event_code');
            }

            if (!Schema::hasColumn('facebook_capi_settings', 'domain_verification_token')) {
                $table->string('domain_verification_token')->nullable()->after('purchase_trigger');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facebook_capi_settings', function (Blueprint $table) {
            if (Schema::hasColumn('facebook_capi_settings', 'domain_verification_token')) {
                $table->dropColumn('domain_verification_token');
            }

            if (Schema::hasColumn('facebook_capi_settings', 'purchase_trigger')) {
                $table->dropColumn('purchase_trigger');
            }
        });
    }
};
