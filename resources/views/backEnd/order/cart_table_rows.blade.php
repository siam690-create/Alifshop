@foreach($cartinfo as $key=>$value)
@php
    $itemDiscount = (float) ($value->options->product_discount ?? 0);
    $linePrice = max(0, ((float) $value->price) - $itemDiscount);
    $lineTotal = $linePrice * (float) $value->qty;
@endphp
<tr>
  <td><img height="30" src="{{asset($value->options->image)}}"></td>
  <td>
      <div class="fw-semibold">{{$value->name}}</div>
  </td>
  @php
      $product = \App\Models\Product::find($value->id);
      $sizesList = collect();
      $colorsList = collect();
      if ($product) {
          $sizeIds = \App\Models\ProductVariantPrice::where('product_id', $product->id)->whereNotNull('size_id')->pluck('size_id')->unique()->filter();
          $colorIds = \App\Models\ProductVariantPrice::where('product_id', $product->id)->whereNotNull('color_id')->pluck('color_id')->unique()->filter();
          if ($sizeIds->isNotEmpty()) {
              $sizesList = \App\Models\Size::whereIn('id', $sizeIds)->get();
          }
          if ($colorIds->isNotEmpty()) {
              $colorsList = \App\Models\Color::whereIn('id', $colorIds)->get();
          }
          if ($sizesList->isEmpty() && $colorsList->isEmpty()) {
              $sizesList = $product->sizes ?? collect();
              $colorsList = $product->colors ?? collect();
          }
      }
      $hasSizes = $sizesList->isNotEmpty();
      $hasColors = $colorsList->isNotEmpty();
      $currentSizeId = $value->options->size_id ?? '';
      $currentColorId = $value->options->color_id ?? '';
  @endphp
  <td style="min-width:120px;">
    @if($hasColors)
      <select class="form-select form-select-sm cart-color-selector" data-id="{{ $value->rowId }}" data-product-id="{{ $value->id }}">
          <option value="">Select</option>
          @foreach($colorsList as $c)
          <option value="{{ $c->id }}" {{ $currentColorId == $c->id ? 'selected' : '' }}>{{ $c->colorName ?? $c->color_name ?? 'N/A' }}</option>
          @endforeach
      </select>
    @else
      <span>{{ $value->options->product_color_name ?? 'N/A' }}</span>
    @endif
  </td>
  <td style="min-width:120px;">
    @if($hasSizes)
      <select class="form-select form-select-sm cart-size-selector" data-id="{{ $value->rowId }}" data-product-id="{{ $value->id }}">
          <option value="">Select</option>
          @foreach($sizesList as $s)
          <option value="{{ $s->id }}" {{ $currentSizeId == $s->id ? 'selected' : '' }}>{{ $s->sizeName ?? $s->size_name ?? 'N/A' }}</option>
          @endforeach
      </select>
    @else
      <span>{{ $value->options->product_size_name ?? 'N/A' }}</span>
    @endif
  </td>
  <td>
    <div class="qty-cart vcart-qty">
      <div class="quantity">
          <button class="minus cart_decrement" value="{{$value->qty}}" data-id="{{$value->rowId}}">-</button>
          <input type="text" inputmode="numeric" pattern="[0-9]*" value="{{$value->qty}}" class="cart_qty_input quick-select-input" data-id="{{$value->rowId}}" />
          <button class="plus cart_increment" value="{{$value->qty}}" data-id="{{$value->rowId}}">+</button>
      </div>
  </div>
  </td>
  <td style="min-width:120px;">
    <input
        type="number"
        min="0"
        step="0.01"
        class="form-control form-control-sm sell_price_input quick-select-input"
        value="{{ number_format((float) $value->price, 2, '.', '') }}"
        data-id="{{ $value->rowId }}"
    >
  </td>
  <td style="min-width:120px;">
    <input
        type="number"
        min="0"
        step="0.01"
        class="form-control form-control-sm admin_price_input quick-select-input"
        value="{{ number_format((float) ($value->options->admin_price ?? $value->price), 2, '.', '') }}"
        data-id="{{ $value->rowId }}"
    >
  </td>
  <td style="min-width:120px;">
    <input
        type="number"
        min="0"
        step="0.01"
        max="{{ (float) $value->price }}"
        class="form-control form-control-sm product_discount quick-select-input"
        value="{{ number_format($itemDiscount, 2, '.', '') }}"
        data-id="{{ $value->rowId }}"
    >
  </td>
  <td>{{ number_format($lineTotal, 2) }}</td>
  <td class="text-center">
    <button type="button" class="btn btn-light btn-sm cart_remove" data-id="{{$value->rowId}}">
        <i class="fa fa-times text-danger"></i>
    </button>
  </td>
</tr>
@endforeach
