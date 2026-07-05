<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'reseller_payout_cycle')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('reseller_payout_cycle', 20)->default('daily');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'reseller_payout_cycle')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('reseller_payout_cycle');
            });
        }
    }
};
