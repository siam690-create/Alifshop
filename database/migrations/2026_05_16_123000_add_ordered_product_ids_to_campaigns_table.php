<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'ordered_product_ids')) {
                $table->longText('ordered_product_ids')->nullable()->after('auto_select_product_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'ordered_product_ids')) {
                $table->dropColumn('ordered_product_ids');
            }
        });
    }
};
