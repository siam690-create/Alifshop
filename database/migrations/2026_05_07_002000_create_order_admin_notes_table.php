<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_admin_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('selected_comment')->nullable();
            $table->text('custom_comment')->nullable();
            $table->text('final_comment');
            $table->timestamps();

            $table->index('order_id');
            $table->index('admin_id');
            $table->index('created_at');
        });

        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->orderBy('id')
                ->chunkById(200, function ($orders) {
                    $rows = [];

                    foreach ($orders as $order) {
                        $adminNote = trim((string) ($order->admin_note ?? ''));
                        $adminNote = trim(str_replace('[PRINTED]', '', $adminNote));

                        if ($adminNote === '') {
                            continue;
                        }

                        $rows[] = [
                            'order_id' => $order->id,
                            'admin_id' => !empty($order->created_by) ? $order->created_by : null,
                            'selected_comment' => null,
                            'custom_comment' => $adminNote,
                            'final_comment' => $adminNote,
                            'created_at' => $order->updated_at ?? $order->created_at ?? now(),
                            'updated_at' => $order->updated_at ?? $order->created_at ?? now(),
                        ];
                    }

                    if (!empty($rows)) {
                        DB::table('order_admin_notes')->insert($rows);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_admin_notes');
    }
};
