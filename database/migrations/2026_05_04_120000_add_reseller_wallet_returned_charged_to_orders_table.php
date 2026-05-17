<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('reseller_wallet_returned_charged')
                ->default(0)
                ->after('reseller_wallet_credited')
                ->comment('Whether reseller wallet has been charged for a returned order');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reseller_wallet_returned_charged');
        });
    }
};
