@extends('backEnd.layouts.master')
@section('title','FB/TikTok CAPI Settings')

@section('css')
<style>
    .card {
        border: none;
        box-shadow: 0 0 20px rgba(18, 38, 63, 0.03);
        border-radius: 12px;
        overflow: hidden;
    }
    .card-header {
        background: #fff;
        border-bottom: 1px solid #f1f5f7;
        padding: 20px 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-title {
        font-size: 16px;
        font-weight: 700;
        color: #2d3436;
        margin: 0;
    }
    .header-icon {
        width: 35px;
        height: 35px;
        background: rgba(10, 207, 151, 0.08);
        color: #0acf97;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .form-label {
        font-weight: 600;
        font-size: 13px;
        color: #636e72;
        margin-bottom: 6px;
    }
    .form-control {
        background-color: #fbfcff;
        border: 1px solid #eef2f7;
        padding: 11px 14px;
        border-radius: 8px;
        font-size: 14px;
    }
    .form-control:focus {
        background-color: #fff;
        border-color: #0acf97;
        box-shadow: 0 0 0 3px rgba(10, 207, 151, 0.15);
    }
    .small-help {
        font-size: 12px;
        color: #95a5a6;
    }
    .btn-submit {
        background: linear-gradient(45deg, #0acf97, #06b6d4);
        border: none;
        color: white;
        padding: 10px 24px;
        font-weight: 600;
        letter-spacing: .4px;
        border-radius: 40px;
        box-shadow: 0 4px 14px rgba(10, 207, 151, 0.35);
    }
    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(10, 207, 151, 0.45);
    }
    .form-switch .form-check-input {
        width: 3rem;
        height: 1.5rem;
    }
    .trigger-option {
        border: 1px solid #eef2f7;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: .2s ease;
    }
    .trigger-option:has(input:checked) {
        border-color: #635bff;
        background: rgba(99, 91, 255, .05);
    }
    .trigger-title {
        color: #1f2937;
        font-weight: 700;
        margin-bottom: 2px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0" style="font-weight: 700; color: #2d3436;">
                    FB/TikTok CAPI Settings
                </h4>
                <p class="text-muted font-size-13 mb-0">
                    Facebook CAPI and TikTok Events API credentials manage করুন।
                </p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card">
                <div class="card-header">
                    <div class="header-icon">
                        <i class="fe-share-2"></i>
                    </div>
                    <h5 class="card-title mb-0">Credentials Configuration</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.facebook_capi.update') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="pixel_id">
                                Facebook Pixel ID <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('pixel_id') is-invalid @enderror"
                                id="pixel_id"
                                name="pixel_id"
                                value="{{ old('pixel_id', $setting->pixel_id ?? '') }}"
                                placeholder="e.g. 123456789012345"
                                required
                            >
                            <small class="small-help">
                                Facebook Events Manager থেকে Pixel ID কপি করে এখানে পেস্ট করুন।
                            </small>
                            @error('pixel_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="access_token">
                                Long-lived Access Token <span class="text-danger">*</span>
                            </label>
                            <textarea
                                class="form-control @error('access_token') is-invalid @enderror"
                                id="access_token"
                                name="access_token"
                                rows="3"
                                placeholder="Paste your long-lived access token here"
                                required
                            >{{ old('access_token', $setting->access_token ?? '') }}</textarea>
                            <small class="small-help">
                                Facebook Developer Tools থেকে generated CAPI এর long-lived access token এখানে রাখবেন।
                            </small>
                            @error('access_token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="test_event_code">
                                Test Event Code (optional)
                            </label>
                            <input
                                type="text"
                                class="form-control @error('test_event_code') is-invalid @enderror"
                                id="test_event_code"
                                name="test_event_code"
                                value="{{ old('test_event_code', $setting->test_event_code ?? '') }}"
                                placeholder="e.g. TEST1234"
                            >
                            <small class="small-help">
                                Events Manager &gt; Test Events থেকে পাওয়া Test Event Code (যদি ব্যবহার করেন)।
                            </small>
                            @error('test_event_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @php
                            $selectedTrigger = old('purchase_trigger', $setting->purchase_trigger ?? 'order_created');
                            $triggerOptions = [
                                'order_created' => [
                                    'title' => 'Order placed',
                                    'help' => 'Best for COD/prepaid ecommerce. Purchase sends as soon as order is created.',
                                ],
                                'order_confirmed' => [
                                    'title' => 'Order confirmed',
                                    'help' => 'Purchase sends when status becomes Pending/Processing/Confirmed.',
                                ],
                                'shipped' => [
                                    'title' => 'Courier shipped',
                                    'help' => 'Purchase sends when status becomes Shipped/In Courier/On The Way.',
                                ],
                                'delivered' => [
                                    'title' => 'Delivered completed',
                                    'help' => 'Highest accuracy, but may send late if delivery update is delayed.',
                                ],
                            ];
                        @endphp
                        <div class="mb-4">
                            <label class="form-label">Purchase Event Trigger</label>
                            <div class="small-help mb-2">Choose when Facebook CAPI should send the Purchase event.</div>
                            @foreach($triggerOptions as $triggerValue => $trigger)
                                <label class="trigger-option d-flex gap-2 align-items-start">
                                    <input
                                        class="form-check-input mt-1"
                                        type="radio"
                                        name="purchase_trigger"
                                        value="{{ $triggerValue }}"
                                        {{ $selectedTrigger === $triggerValue ? 'checked' : '' }}
                                    >
                                    <span>
                                        <span class="trigger-title d-block">{{ $trigger['title'] }}</span>
                                        <span class="small-help">{{ $trigger['help'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                            @error('purchase_trigger')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="domain_verification_token">
                                Facebook Domain Verification Token (optional)
                            </label>
                            <input
                                type="text"
                                class="form-control @error('domain_verification_token') is-invalid @enderror"
                                id="domain_verification_token"
                                name="domain_verification_token"
                                value="{{ old('domain_verification_token', $setting->domain_verification_token ?? '') }}"
                                placeholder="Paste only the content value from Meta verification meta tag"
                            >
                            <small class="small-help">
                                Meta Business Settings থেকে পাওয়া meta tag-এর content value দিন. Save করলে frontend head-এ automatically add হবে.
                            </small>
                            @error('domain_verification_token')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4 form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="status"
                                name="status"
                                value="1"
                                {{ old('status', $setting->status ?? 1) ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="status">
                                Facebook CAPI Active রাখুন
                            </label>
                        </div>

                        <div class="card mt-4 mb-4 border">
                            <div class="card-header">
                                <div class="header-icon">
                                    <i class="fab fa-tiktok"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-1">TikTok Pixel & Events API</h5>
                                    <p class="small-help mb-0">Add TikTok Pixel ID and Events API token for browser + server-side purchase tracking.</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label" for="tiktok_pixel_id">TikTok Pixel ID</label>
                                    <input
                                        type="text"
                                        class="form-control @error('tiktok_pixel_id') is-invalid @enderror"
                                        id="tiktok_pixel_id"
                                        name="tiktok_pixel_id"
                                        value="{{ old('tiktok_pixel_id', $setting->tiktok_pixel_id ?? '') }}"
                                        placeholder="e.g. CXXXXXXXXXXXXXXXXX"
                                    >
                                    <small class="small-help">TikTok Ads Manager &gt; Assets &gt; Events &gt; Web Events - copy your Pixel ID.</small>
                                    @error('tiktok_pixel_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="tiktok_access_token">Events API Access Token</label>
                                    <textarea
                                        class="form-control @error('tiktok_access_token') is-invalid @enderror"
                                        id="tiktok_access_token"
                                        name="tiktok_access_token"
                                        rows="3"
                                        placeholder="Paste TikTok Events API access token here"
                                    >{{ old('tiktok_access_token', $setting->tiktok_access_token ?? '') }}</textarea>
                                    <small class="small-help">TikTok Events Manager &gt; Settings &gt; Events API &gt; Generate Access Token.</small>
                                    @error('tiktok_access_token')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="tiktok_test_event_code">Test Event Code (optional)</label>
                                    <input
                                        type="text"
                                        class="form-control @error('tiktok_test_event_code') is-invalid @enderror"
                                        id="tiktok_test_event_code"
                                        name="tiktok_test_event_code"
                                        value="{{ old('tiktok_test_event_code', $setting->tiktok_test_event_code ?? '') }}"
                                        placeholder="e.g. TEST12345"
                                    >
                                    <small class="small-help">Use only while testing in TikTok Events Manager. Remove it before live ads.</small>
                                    @error('tiktok_test_event_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-0 form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="tiktok_status"
                                        name="tiktok_status"
                                        value="1"
                                        {{ old('tiktok_status', $setting->tiktok_status ?? 0) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="tiktok_status">
                                        TikTok Pixel & Events API Active
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-submit">
                                <i class="fe-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

