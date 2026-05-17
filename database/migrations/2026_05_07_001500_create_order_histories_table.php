<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('event_type', 50);
            $table->string('title');
            $table->string('status_name')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_type', 30)->nullable();
            $table->text('description')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status_id');
            $table->index('changed_by');
            $table->index('event_type');
            $table->index('created_at');
        });

        $statuses = DB::table('order_statuses')->pluck('name', 'id');
        $users = DB::table('users')->pluck('name', 'id');
        $now = now();

        DB::table('orders')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($statuses, $users, $now) {
                $rows = [];

                foreach ($orders as $order) {
                    $statusId = (int) ($order->order_status ?? 0);
                    $statusName = $statuses[$statusId] ?? null;
                    $changedBy = null;
                    $actorName = 'Customer';
                    $actorType = 'customer';

                    if (!empty($order->created_by) && isset($users[$order->created_by])) {
                        $changedBy = $order->created_by;
                        $actorName = $users[$order->created_by];
                        $actorType = 'admin';
                    } elseif (!empty($order->user_id) && isset($users[$order->user_id]) && (float) ($order->reseller_profit ?? 0) > 0) {
                        $changedBy = $order->user_id;
                        $actorName = $users[$order->user_id];
                        $actorType = 'reseller';
                    }

                    $createdAt = $order->created_at ?: $now;

                    $rows[] = [
                        'order_id' => $order->id,
                        'status_id' => $statusId ?: null,
                        'changed_by' => $changedBy,
                        'event_type' => 'created',
                        'title' => 'Order Created',
                        'status_name' => $statusName,
                        'actor_name' => $actorName,
                        'actor_type' => $actorType,
                        'description' => 'Initial history imported from the existing order record.',
                        'changes' => json_encode([
                            'Status' => ['new' => $statusName],
                            'Amount' => ['new' => (string) ($order->amount ?? '')],
                        ], JSON_UNESCAPED_UNICODE),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('order_histories')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
