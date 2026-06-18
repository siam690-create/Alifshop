<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Print</title>
    <style>
        :root {
            --receipt-width: 3in;
            --receipt-height: 4in;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 12px 0;
            background: #f3f4f6;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }

        .no-print {
            text-align: center;
            margin-bottom: 12px;
        }

        .no-print button {
            border: none;
            background: #111827;
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .receipt {
            width: var(--receipt-width);
            height: var(--receipt-height);
            margin: 0 auto 14px;
            background: #fff;
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 6px;
        }

        .receipt-logo {
            max-width: 135px;
            max-height: 30px;
            object-fit: contain;
            margin-bottom: 6px;
        }

        .top-meta {
            text-align: left;
            font-size: 11px;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .meta-line {
            display: flex;
            gap: 4px;
            margin-bottom: 2px;
        }

        .meta-label {
            min-width: 58px;
            font-weight: 700;
        }

        .meta-value {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
            flex-shrink: 0;
        }

        .info-block {
            font-size: 11px;
            line-height: 1.2;
            flex-shrink: 0;
        }

        .info-row {
            display: flex;
            gap: 4px;
            margin-bottom: 2px;
        }

        .info-row .label {
            min-width: 52px;
            font-weight: 700;
        }

        .info-row .value {
            flex: 1;
            min-width: 0;
        }

        .clamp-1,
        .clamp-2 {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .clamp-1 {
            -webkit-line-clamp: 1;
            line-clamp: 1;
        }

        .clamp-2 {
            -webkit-line-clamp: 2;
            line-clamp: 2;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            line-height: 1.2;
            margin: 2px 0;
            flex-shrink: 0;
        }

        .invoice-row span strong {
            font-weight: 700;
        }

        .product-block {
            font-size: 11px;
            line-height: 1.2;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .product-line {
            margin-bottom: 4px;
        }

        .product-line .label {
            font-weight: 700;
            display: block;
            margin-bottom: 1px;
        }
        .product-item-list {
            margin-top: 2px;
        }
        .product-item-entry {
            margin-bottom: 6px;
            padding-bottom: 5px;
            border-bottom: 1px dotted #d1d5db;
        }
        .product-item-entry:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .product-item-entry .name {
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 2px;
        }
        .product-item-entry .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            color: #111827;
            line-height: 1.2;
        }

        .variant-qty-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .variant-box,
        .qty-box {
            width: 48%;
        }

        .cod-row {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            padding-top: 2px;
            flex-shrink: 0;
        }

        .receipt-footer {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
            flex-shrink: 0;
        }

        @page {
            size: 3in 4in;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .receipt {
                margin: 0;
                border: none;
                page-break-after: always;
            }

            .receipt:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print</button>
    </div>

    @php
        $defaultSitePhone = trim((string) (optional(\App\Models\Contact::query()->select('phone')->first())->phone ?? ''));
    @endphp

    @foreach($orders as $order)
        @php
            $shipping = $order->shipping;
            $customerName = $shipping->name ?? optional($order->customer)->name ?? 'N/A';
            $customerPhone = $shipping->phone ?? optional($order->customer)->phone ?? 'N/A';
            $customerAddress = trim((string) ($shipping->address ?? '')) ?: 'N/A';

            $siteShopName = trim((string) ($generalsetting->name ?? config('app.name', 'Alifshop')));
            $reseller = $order->user;
            $isResellerOrder = !is_null($order->reseller_profit) && (float) $order->reseller_profit > 0 && !empty($reseller);
            $resellerShopName = trim((string) ($reseller->shop_name ?? optional(optional($reseller)->vendor)->shop_name ?? ''));
            $printShopName = $isResellerOrder && $resellerShopName !== ''
                ? $resellerShopName
                : ($siteShopName !== '' ? $siteShopName : 'Alifshop');
            $resellerProfilePhone = null;
            if (!empty($reseller?->email)) {
                $resellerProfilePhone = optional(\App\Models\Customer::where('email', $reseller->email)->select('phone')->first())->phone;
            }
            $resellerPhone = trim((string) ($resellerProfilePhone ?? optional(optional($reseller)->vendor)->phone ?? ''));
            $shopPhoneDisplay = $isResellerOrder && $resellerPhone !== ''
                ? $resellerPhone
                : ($defaultSitePhone !== '' ? $defaultSitePhone : 'N/A');

            $firstDetail = $order->orderdetails->first();

            $variantParts = [];
            if (!empty($firstDetail?->size) && !empty($firstDetail->size->sizeName)) {
                $variantParts[] = $firstDetail->size->sizeName;
            } elseif (!empty($firstDetail?->product_size)) {
                $variantParts[] = $firstDetail->product_size;
            }
            if (!empty($firstDetail?->color) && !empty($firstDetail->color->colorName)) {
                $variantParts[] = $firstDetail->color->colorName;
            } elseif (!empty($firstDetail?->product_color)) {
                $variantParts[] = $firstDetail->product_color;
            }
            $variantText = !empty($variantParts) ? implode(', ', $variantParts) : 'N/A';

            $qtyText = (string) ($order->orderdetails->sum('qty') ?: ($firstDetail->qty ?? 1));
            $codAmount = number_format($order->customer_payable_amount ?? $order->amount ?? 0, 0);
            $productLines = $order->orderdetails->map(function ($detail) {
                $detailVariantParts = [];
                if (!empty($detail?->size) && !empty($detail->size->sizeName)) {
                    $detailVariantParts[] = $detail->size->sizeName;
                } elseif (!empty($detail?->product_size)) {
                    $detailVariantParts[] = $detail->product_size;
                }
                if (!empty($detail?->color) && !empty($detail->color->colorName)) {
                    $detailVariantParts[] = $detail->color->colorName;
                } elseif (!empty($detail?->product_color)) {
                    $detailVariantParts[] = $detail->product_color;
                }

                return [
                    'name' => $detail->product_name ?: 'Product',
                    'code' => trim((string) (optional($detail->product)->product_code ?? '')) ?: 'N/A',
                    'variant' => !empty($detailVariantParts) ? implode(', ', $detailVariantParts) : 'N/A',
                    'qty' => (string) ($detail->qty ?: 1),
                ];
            });
        @endphp

        <div class="receipt">
            <div class="receipt-header">
                @if(isset($generalsetting) && !empty($generalsetting->dark_logo))
                    <img src="{{ asset($generalsetting->dark_logo) }}" alt="{{ $siteShopName ?: 'Alifshop' }}" class="receipt-logo">
                @elseif(isset($generalsetting) && !empty($generalsetting->white_logo))
                    <img src="{{ asset($generalsetting->white_logo) }}" alt="{{ $siteShopName ?: 'Alifshop' }}" class="receipt-logo">
                @else
                    <div style="font-weight:700; font-size:16px;">{{ $siteShopName ?: 'Alifshop' }}</div>
                @endif
            </div>

            <div class="top-meta">
                <div class="meta-line">
                    <span class="meta-label">Shop Name :</span>
                    <span class="meta-value">{{ $printShopName }}</span>
                </div>
                <div class="meta-line">
                    <span class="meta-label">Phone No. :</span>
                    <span class="meta-value">{{ $shopPhoneDisplay }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="info-block">
                <div class="info-row">
                    <span class="label">Name :</span>
                    <span class="value clamp-1"><strong>{{ $customerName }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Phone :</span>
                    <span class="value clamp-1"><strong>{{ $customerPhone }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Address :</span>
                    <span class="value clamp-2">{{ $customerAddress }}</span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="invoice-row">
                <span><strong>Invoice :</strong> {{ $order->invoice_id ?? $order->id }}</span>
                <span><strong>Date :</strong> {{ $order->created_at->format('d-m-y') }}</span>
            </div>

            <div class="divider"></div>

            <div class="product-block">
                <div class="product-line">
                    <span class="label">Products :</span>
                    <div class="product-item-list">
                        @foreach($productLines as $line)
                            <div class="product-item-entry">
                                <div class="name clamp-2">{{ $line['name'] }}</div>
                                <div class="meta">
                                    <span><strong>Code:</strong> {{ $line['code'] }}</span>
                                    <span><strong>Qty:</strong> {{ $line['qty'] }}</span>
                                    <span><strong>Variant:</strong> {{ $line['variant'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="variant-qty-row">
                    <div class="variant-box">
                        <span class="label">Total Variant :</span>
                        <div class="clamp-1">{{ $variantText }}</div>
                    </div>
                    <div class="qty-box">
                        <span class="label">Total Qty :</span>
                        <div class="clamp-1">{{ $qtyText }}</div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="cod-row">COD : &#2547;{{ $codAmount }}</div>

            <div class="divider"></div>

            <div class="receipt-footer">Thank you for order</div>
        </div>
    @endforeach
</body>
</html>
