<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'sold')) {
                $table->integer('sold')->default(0)->after('old_price');
            }

            if (!Schema::hasColumn('products', 'flashsale')) {
                $table->tinyInteger('flashsale')->default(0)->after('feature_product');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'icon')) {
                $table->string('icon')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sold')) {
                $table->dropColumn('sold');
            }

            if (Schema::hasColumn('products', 'flashsale')) {
                $table->dropColumn('flashsale');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
