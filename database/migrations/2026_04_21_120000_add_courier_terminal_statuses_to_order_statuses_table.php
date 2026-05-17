<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $statuses = [
            [
                'name' => 'Delivered',
                'slug' => 'delivered',
                'status' => 1,
            ],
            [
                'name' => 'Partial Delivered',
                'slug' => 'partial-delivered',
                'status' => 1,
            ],
            [
                'name' => 'Returned',
                'slug' => 'returned',
                'status' => 1,
            ],
        ];

        foreach ($statuses as $status) {
            $existing = DB::table('order_statuses')
                ->where('slug', $status['slug'])
                ->orWhere('name', $status['name'])
                ->first();

            if ($existing) {
                DB::table('order_statuses')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $status['name'],
                        'slug' => $status['slug'],
                        'status' => $status['status'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('order_statuses')->insert([
                    'name' => $status['name'],
                    'slug' => $status['slug'],
                    'status' => $status['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Cache::forget('order_status_list');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('order_statuses')
            ->whereIn('slug', ['delivered', 'partial-delivered', 'returned'])
            ->delete();

        Cache::forget('order_status_list');
    }
};
