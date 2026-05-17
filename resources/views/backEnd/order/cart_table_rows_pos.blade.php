@foreach($cartinfo as $key => $value)
@php
    $itemDiscount = (float) ($value->options->product_discount ?? 0);
    $lineTotal = max(0, ((float) $value->price - $itemDiscount)) * (float) $value->qty;
@endphp
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>
        <div class="d-flex align-items-center gap-2">
            <img height="36" src="{{ asset($value->options->image) }}">
            <div class="fw-semibold">{{ $value->name }}</div>
        </div>
        @if(!empty($value->options->product_color))
            <div class="small text-muted">Color: {{ $value->options->product_color }}</div>
        @endif
        @if(!empty($value->options->product_size))
            <div class="small text-muted">Size: {{ $value->options->product_size }}</div>
        @endif
    </td>
    <td>
        <div class="qty-cart vcart-qty">
            <div class="quantity">
                <button class="minus cart_decrement" value="{{ $value->qty }}" data-id="{{ $value->rowId }}">-</button>
                <input type="number" min="1" step="1" value="{{ $value->qty }}" class="cart_qty_input" data-id="{{ $value->rowId }}" />
                <button class="plus cart_increment" value="{{ $value->qty }}" data-id="{{ $value->rowId }}">+</button>
            </div>
        </div>
    </td>
    <td style="min-width:110px;">
        <input
            type="number"
            min="0"
            step="0.01"
            class="form-control form-control-sm sell_price_input"
            value="{{ number_format((float) $value->price, 2, '.', '') }}"
            data-id="{{ $value->rowId }}"
        >
    </td>
    <td style="min-width:110px;">
        <input
            type="number"
            min="0"
            step="0.01"
            class="form-control form-control-sm admin_price_input"
            value="{{ number_format((float) ($value->options->admin_price ?? $value->price), 2, '.', '') }}"
            data-id="{{ $value->rowId }}"
        >
    </td>
    <td style="min-width:110px;">
        <input
            type="number"
            min="0"
            step="0.01"
            max="{{ (float) $value->price }}"
            class="form-control form-control-sm product_discount"
            value="{{ number_format($itemDiscount, 2, '.', '') }}"
            data-id="{{ $value->rowId }}"
        >
    </td>
    <td>{{ number_format($lineTotal, 2) }}</td>
    <td class="text-center">
        <button type="button" class="btn btn-light btn-sm cart_remove" data-id="{{ $value->rowId }}">
            <i class="fa fa-times text-danger"></i>
        </button>
    </td>
</tr>
@endforeach
