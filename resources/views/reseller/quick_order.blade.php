@extends('reseller.layouts.app')

@section('title', 'Quick Order - ' . $product->name)
@section('page-title', 'কুইক অর্ডার')

@push('styles')
<style>
    .quick-order-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 12px 24px -18px rgba(15, 23, 42, 0.35);
    }
    .quick-order-header {
        padding: 22px 24px;
        border-bottom: 1px solid #eef2f7;
    }
    .quick-order-body {
        padding: 24px;
    }
    .quick-order-product {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 18px;
        align-items: center;
    }
    .quick-order-product img {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 14px;
        background: #f8fafc;
    }
    .price-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ecfdf5;
        color: #059669;
        font-weight: 700;
        padding: 8px 12px;
        border-radius: 999px;
    }
    .summary-box {
        background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
        border: 1px solid #dbeafe;
        border-radius: 16px;
        padding: 20px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        color: #334155;
    }
    .summary-row:last-child {
        margin-bottom: 0;
    }
    .summary-row.total {
        padding-top: 12px;
        border-top: 1px dashed #bfdbfe;
        font-size: 20px;
        font-weight: 800;
        color: #1d4ed8;
    }
    .section-title {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
    }
    .variant-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .variant-pills .form-check {
        margin: 0;
    }
    .variant-pills .form-check-input {
        display: none;
    }
    .variant-pills .form-check-label {
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        background: #fff;
    }
    .variant-pills .form-check-input:checked + .form-check-label {
        border-color: #2563eb;
        background: #eff6ff;
        color: #1d4ed8;
    }
    @media (max-width: 767px) {
        .quick-order-product {
            grid-template-columns: 1fr;
        }
        .quick-order-product img {
            width: 100%;
            height: 220px;
        }
    }
</style>
@endpush

@section('content')
@php
    $productcolors = $product->variantPrices->pluck('color')->unique('id')->filter();
    $productsizes = $product->variantPrices->pluck('size')->unique('id')->filter();
    $defaultArea = $shippingcharge->first();
@endphp

<div class="row g-4">
    <div class="col-xl-8">
        <form action="{{ route('reseller.products.quick_order.store', $product->slug) }}" method="POST" id="quickOrderForm">
            @csrf
            @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-left: 5px solid #dc3545 !important;">
                <div class="d-flex align-items-start gap-3">
                    <i class="fas fa-exclamation-circle fs-4 mt-1"></i>
                    <div>
                        <strong>অর্ডার সাবমিট হয়নি। নিচের তথ্যগুলো ঠিক করুন:</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif
            <div class="quick-order-card mb-4">
                <div class="quick-order-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">কাস্টমার তথ্য দিয়ে সরাসরি অর্ডার করুন</h4>
                            <p class="text-muted mb-0">কার্ট বা checkout ছাড়াই এই পেজ থেকেই অর্ডার complete করা যাবে।</p>
                        </div>
                        <a href="{{ route('reseller.products.show', $product->slug) }}" class="btn btn-outline-secondary rounded-pill px-3">
                            <i class="fas fa-arrow-left me-1"></i> পণ্যে ফিরে যান
                        </a>
                    </div>
                </div>
                <div class="quick-order-body">
                    <div class="quick-order-product mb-4">
                        <img src="{{ asset($product->image && $product->image->image ? $product->image->image : 'storage/uploads/placeholder.png') }}" alt="{{ $product->name }}">
                        <div>
                            <h4 class="fw-bold text-dark mb-2">{{ $product->name }}</h4>
                            <p class="text-muted mb-3">{{ $product->category->name ?? 'N/A' }} @if($product->brand) / {{ $product->brand->name }} @endif</p>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <span class="price-chip">রিসেলার প্রাইস: ৳<span id="baseResellerPrice">{{ number_format($product->reseller_price, 0, '.', '') }}</span></span>
                                <span class="price-chip" style="background:#eff6ff;color:#1d4ed8;">লাভ: ৳<span id="expectedProfit">{{ number_format($product->profit, 0, '.', '') }}</span></span>
                            </div>
                            <small class="text-muted d-block">স্টক: <span id="availableStock">{{ $product->stock }}</span></small>
                        </div>
                    </div>

                    <div class="row g-3">
                        @if($productcolors->count() > 0)
                        <div class="col-12">
                            <label class="section-title">কালার নির্বাচন করুন</label>
                            <div class="variant-pills">
                                @foreach($productcolors as $procolor)
                                <div class="form-check">
                                    <input class="form-check-input color-radio" type="radio" name="product_color" id="quick-color-{{ $procolor->id }}" value="{{ $procolor->id }}" {{ old('product_color') == $procolor->id ? 'checked' : '' }}>
                                    <label class="form-check-label" for="quick-color-{{ $procolor->id }}">
                                        {{ $procolor->colorName ?? $procolor->name ?? 'Color' }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if($productsizes->count() > 0)
                        <div class="col-md-6">
                            <label class="section-title">সাইজ নির্বাচন করুন</label>
                            <select name="product_size" id="product_size" class="form-select">
                                <option value="">সাইজ নির্বাচন করুন</option>
                                @foreach($productsizes as $prosize)
                                <option value="{{ $prosize->id }}" {{ old('product_size') == $prosize->id ? 'selected' : '' }}>
                                    {{ $prosize->sizeName ?? $prosize->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="col-md-6">
                            <label class="section-title">পরিমাণ</label>
                            <input type="number" name="qty" id="product_qty" class="form-control" min="1" max="{{ $product->stock }}" value="{{ old('qty', 1) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="section-title">আপনার সেল প্রাইস</label>
                            <input type="number" step="0.01" min="{{ $product->reseller_price }}" name="custom_price" id="custom_price" class="form-control" value="{{ old('custom_price', $product->reseller_price) }}">
                            <small class="text-muted">এই price-টাই customer product price হিসেবে save হবে।</small>
                        </div>

                        <div class="col-md-6">
                            <label class="section-title">পেমেন্ট মেথড</label>
                            <select name="payment_method" id="payment_method" class="form-select">
                                <option value="cod" {{ old('payment_method') == 'cod' ? 'selected' : '' }}>Cash On Delivery</option>
                                @if($bkash_gateway)
                                <option value="bkash" {{ old('payment_method') == 'bkash' ? 'selected' : '' }}>bKash</option>
                                @endif
                                @if($shurjopay_gateway)
                                <option value="shurjopay" {{ old('payment_method') == 'shurjopay' ? 'selected' : '' }}>ShurjoPay</option>
                                @endif
                                @if($uddoktapay_gateway)
                                <option value="uddoktapay" {{ old('payment_method') == 'uddoktapay' ? 'selected' : '' }}>UddoktaPay</option>
                                @endif
                                @if($aamarpay_gateway)
                                <option value="aamarpay" {{ old('payment_method') == 'aamarpay' ? 'selected' : '' }}>AamarPay</option>
                                @endif
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="quick-order-card">
                <div class="quick-order-header">
                    <h5 class="fw-bold mb-0">কাস্টমার ইনফরমেশন</h5>
                </div>
                <div class="quick-order-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">কাস্টমারের নাম</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">মোবাইল নাম্বার</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">এরিয়া / শিপিং</label>
                            <select name="area" id="shipping_area" class="form-select" required>
                                @if((int) ($product->free_delivery ?? 0) === 1)
                                <option value="free_shipping" data-charge="0" {{ old('area') == 'free_shipping' ? 'selected' : '' }}>ফ্রি শিপিং</option>
                                @endif
                                @foreach($shippingcharge as $charge)
                                <option value="{{ $charge->id }}" data-charge="{{ $charge->amount }}" {{ (string) old('area', optional($defaultArea)->id) === (string) $charge->id ? 'selected' : '' }}>
                                    {{ $charge->name }} - ৳{{ number_format($charge->amount, 0) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">অর্ডার নোট</label>
                            <input type="text" name="note" class="form-control" value="{{ old('note') }}" placeholder="ঐচ্ছিক note">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">সম্পূর্ণ ঠিকানা</label>
                            <textarea name="address" class="form-control" rows="4" required>{{ old('address') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-xl-4">
        <div class="summary-box sticky-top" style="top: 90px;">
            <h5 class="fw-bold mb-4">অর্ডার সামারি</h5>
            <div class="summary-row">
                <span>রিসেলার মোট দাম</span>
                <strong>৳<span id="summaryResellerTotal">{{ number_format($product->reseller_price, 0, '.', '') }}</span></strong>
            </div>
            <div class="summary-row">
                <span>কাস্টমার প্রোডাক্ট প্রাইস</span>
                <strong>৳<span id="summaryCustomPrice">{{ number_format($product->reseller_price, 0, '.', '') }}</span></strong>
            </div>
            <div class="summary-row">
                <span>শিপিং চার্জ</span>
                <strong>৳<span id="summaryShipping">{{ $defaultArea ? number_format($defaultArea->amount, 0, '.', '') : '0' }}</span></strong>
            </div>
            <div class="summary-row">
                <span>আপনার লাভ</span>
                <strong class="text-success">৳<span id="summaryProfit">{{ number_format($product->profit, 0, '.', '') }}</span></strong>
            </div>
            <div class="summary-row total">
                <span>Customer Total</span>
                <span>৳<span id="summaryGrandTotal">{{ number_format($product->reseller_price + ($defaultArea->amount ?? 0), 0, '.', '') }}</span></span>
            </div>

            <button type="submit" form="quickOrderForm" class="btn btn-primary w-100 rounded-pill py-3 fw-bold mt-4" id="quickOrderSubmitBtn">
                <i class="fas fa-paper-plane me-2"></i> অর্ডার কনফার্ম করুন
            </button>

            <a href="{{ route('reseller.products.show', $product->slug) }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 mt-3">
                বিস্তারিত পেজে যান
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function updateQuickOrderSummary() {
        const qty = Math.max(parseInt(document.getElementById('product_qty').value || 1, 10), 1);
        const baseResellerPrice = parseFloat(@json((float) $product->reseller_price));
        const customPrice = parseFloat(document.getElementById('custom_price').value || 0);
        const shippingSelect = document.getElementById('shipping_area');
        const shippingCharge = shippingSelect ? parseFloat(shippingSelect.options[shippingSelect.selectedIndex]?.dataset.charge || 0) : 0;

        const resellerTotal = baseResellerPrice * qty;
        const profit = Math.max(customPrice - resellerTotal, 0);
        const grandTotal = customPrice + shippingCharge;

        document.getElementById('summaryResellerTotal').textContent = resellerTotal.toFixed(0);
        document.getElementById('summaryCustomPrice').textContent = customPrice.toFixed(0);
        document.getElementById('summaryShipping').textContent = shippingCharge.toFixed(0);
        document.getElementById('summaryProfit').textContent = profit.toFixed(0);
        document.getElementById('summaryGrandTotal').textContent = grandTotal.toFixed(0);
        document.getElementById('expectedProfit').textContent = profit.toFixed(0);
    }

    document.getElementById('product_qty').addEventListener('input', updateQuickOrderSummary);
    document.getElementById('custom_price').addEventListener('input', updateQuickOrderSummary);
    document.getElementById('shipping_area').addEventListener('change', updateQuickOrderSummary);
    document.getElementById('quickOrderForm').addEventListener('submit', function(e) {
        const firstInvalidField = this.querySelector(':invalid');
        const submitButton = document.getElementById('quickOrderSubmitBtn');

        if (firstInvalidField) {
            e.preventDefault();
            firstInvalidField.reportValidity();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>অর্ডার সাবমিট হচ্ছে...';
    });
    updateQuickOrderSummary();
</script>
@endpush
