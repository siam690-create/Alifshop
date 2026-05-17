<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('general_settings') && !DB::table('general_settings')->where('status', 1)->exists()) {
            $payload = [
                'name' => config('app.name', 'SellwayBD'),
                'white_logo' => 'public/logo.png',
                'dark_logo' => 'public/logo.png',
                'favicon' => 'favicon.ico',
                'copyright' => 'All rights reserved.',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $optionalColumns = [
                'primary_color' => '#0d6efd',
                'secodery_color' => '#198754',
                'footer_color' => '#222222',
                'copyright_color' => '#111111',
                'facebook_page_username' => '',
                'og_baner' => 'public/logo.png',
                'show_category_wise_products' => 1,
                'show_all_products' => 1,
                'footer_about_text' => 'Your trusted online store.',
                'vendor_enabled' => 1,
                'reseller_enabled' => 1,
            ];

            foreach ($optionalColumns as $column => $value) {
                if (Schema::hasColumn('general_settings', $column)) {
                    $payload[$column] = $value;
                }
            }

            DB::table('general_settings')->insert($payload);
        }

        if (Schema::hasTable('seo_settings') && !DB::table('seo_settings')->exists()) {
            DB::table('seo_settings')->insert([
                'meta_title' => config('app.name', 'SellwayBD'),
                'meta_tags' => null,
                'meta_description' => null,
                'search_console_verification' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seo_settings')) {
            DB::table('seo_settings')
                ->where('meta_title', config('app.name', 'SellwayBD'))
                ->whereNull('meta_tags')
                ->delete();
        }

        if (Schema::hasTable('general_settings')) {
            DB::table('general_settings')
                ->where('name', config('app.name', 'SellwayBD'))
                ->where('dark_logo', 'public/logo.png')
                ->where('white_logo', 'public/logo.png')
                ->delete();
        }
    }
};
