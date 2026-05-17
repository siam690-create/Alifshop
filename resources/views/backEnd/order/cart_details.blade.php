@php
    $subtotalRaw = Cart::instance('pos_shopping')->subtotal();
    $subtotalNum = (float) preg_replace('/[^\d.]/', '', (string) $subtotalRaw);
    $shippingNum = (float) (Session::get('pos_shipping') ?? 0);
    $couponDiscount = (float) (Session::get('pos_discount') ?? 0);
    $productDiscount = Cart::instance('pos_shopping')->content()->sum(function ($item) {
        return ((float) ($item->options->product_discount ?? 0)) * ((float) $item->qty);
    });
    $totalDiscount = $couponDiscount + $productDiscount;
    $grandTotal = max(0, $subtotalNum + $shippingNum - $totalDiscount);
@endphp
<tr>
    <td>Sub Total</td>
    <td class="text-end">Tk {{ number_format($subtotalNum, 2) }}</td>
</tr>
<tr>
    <td>Product Discount</td>
    <td class="text-end">Tk {{ number_format($productDiscount, 2) }}</td>
</tr>
<tr>
    <td>Shipping Fee</td>
    <td class="text-end">
        <input
            type="number"
            min="0"
            step="0.01"
            name="shipping_charge"
            id="shipping_charge"
            class="form-control form-control-sm text-end d-inline-block"
            value="{{ number_format($shippingNum, 2, '.', '') }}"
            style="max-width: 140px;"
        >
    </td>
</tr>
<tr>
    <td>Coupon Discount</td>
    <td class="text-end">Tk {{ number_format($couponDiscount, 2) }}</td>
</tr>
<tr>
    <td>Total Discount</td>
    <td class="text-end">Tk {{ number_format($totalDiscount, 2) }}</td>
</tr>
<tr>
    <td><strong>Grand Total</strong></td>
    <td class="text-end pos-grand-total">Tk {{ number_format($grandTotal, 2) }}</td>
</tr>
