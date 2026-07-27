<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacebookCapiSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Toastr;

class FacebookCapiSettingController extends Controller
{
    /**
     * Show edit form (single settings row).
     */
    public function edit()
    {
        $setting = FacebookCapiSetting::first();

        if (!$setting) {
            $setting = new FacebookCapiSetting([
                'status' => 1,
            ]);
        }

        return view('backEnd.settings.facebook_capi', compact('setting'));
    }

    /**
     * Save / update Facebook CAPI credentials.
     */
    public function update(Request $request)
    {
        $request->validate([
            'pixel_id'        => 'required|string|max:255',
            'access_token'    => 'required|string',
            'test_event_code' => 'nullable|string|max:255',
            'purchase_trigger' => ['nullable', Rule::in(['order_created', 'order_confirmed', 'shipped', 'delivered'])],
            'domain_verification_token' => 'nullable|string|max:255',
            'tiktok_pixel_id' => 'nullable|string|max:255',
            'tiktok_access_token' => 'nullable|string',
            'tiktok_test_event_code' => 'nullable|string|max:255',
            'tiktok_status' => 'nullable|boolean',
            'status'          => 'nullable|boolean',
        ]);

        $tokenInput = trim((string) $request->domain_verification_token);
        if (preg_match('/content=["\']([^"\']+)["\']/i', $tokenInput, $matches)) {
            $tokenInput = $matches[1];
        }

        $data = [
            'pixel_id'        => $request->pixel_id,
            'access_token'    => $request->access_token,
            'test_event_code' => $request->test_event_code,
            'purchase_trigger' => $request->purchase_trigger ?: 'order_created',
            'domain_verification_token' => $tokenInput,
            'tiktok_pixel_id' => $request->tiktok_pixel_id,
            'tiktok_access_token' => $request->tiktok_access_token,
            'tiktok_test_event_code' => $request->tiktok_test_event_code,
            'tiktok_status' => $request->has('tiktok_status') ? 1 : 0,
            'status'          => 1,
        ];

        // Use firstOrCreate to ensure only one record exists
        $setting = FacebookCapiSetting::first();
        if ($setting) {
            $setting->update($data);
        } else {
            FacebookCapiSetting::create($data);
        }

        // Clear cache so new settings are loaded immediately
        Cache::forget('facebook_capi_settings');
        Cache::forget('facebook_capi_domain_verification_token');
        Cache::forget('tiktok_events_api_settings');
        Cache::forget('tiktok_browser_pixel_id');

        Toastr::success('Tracking API settings updated successfully', 'Success');

        return redirect()->route('admin.facebook_capi.edit');
    }
}
