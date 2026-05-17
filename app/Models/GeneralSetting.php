<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    use HasFactory;
    protected $guarded = [];

    public static function defaultAttributes(): array
    {
        return [
            'name' => config('app.name', 'SellwayBD'),
            'white_logo' => 'public/logo.png',
            'dark_logo' => 'public/logo.png',
            'favicon' => 'favicon.ico',
            'copyright' => 'All rights reserved.',
            'status' => 1,
            'primary_color' => '#0d6efd',
            'secodery_color' => '#198754',
            'footer_color' => '#222222',
            'copyright_color' => '#111111',
            'facebook_page_username' => '',
            'og_baner' => 'public/logo.png',
            'show_category_wise_products' => 1,
            'show_all_products' => 1,
            'footer_about_text' => 'Your trusted online store.',
        ];
    }

    public static function activeOrDefault(): self
    {
        return static::where('status', 1)->first() ?? new static(static::defaultAttributes());
    }
}
