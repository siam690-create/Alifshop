@extends('backEnd.layouts.master') 
@section('title','Order Create') 
@section('css')
<style>
    body { background: #f3f6f9; }
    .order-simple-shell {
        background: #f3f6f9;
        padding: 24px 14px 38px;
    }
    .order-back-link {
        background: #15c7aa;
        border-radius: 4px;
        color: #fff;
        display: inline-block;
        font-weight: 700;
        padding: 9px 13px;
    }
    .order-back-link:hover { color: #fff; opacity: .92; }
    .order-simple-card {
        background: #fff;
        border: 0;
        border-radius: 8px;
        overflow: visible;
    }
    .order-simple-card .card-heading {
        border-bottom: 1px solid #edf0f4;
        color: #1d2636;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: .06em;
        margin: 0;
        padding: 20px 24px;
        text-align: center;
    }
    .order-simple-card .card-body { padding: 22px 24px 26px; }
    .order-simple-card label {
        color: #111827;
        font-size: 14px;
        margin-bottom: 9px;
    }
    .order-simple-card .form-control,
    .order-simple-card .form-select,
    .order-simple-card .select2-container .select2-selection--single {
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        min-height: 46px;
    }
    .order-simple-card .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 44px;
    }
    .order-simple-card .select2-container .select2-selection--single .select2-selection__arrow {
        height: 44px;
    }
    .product-table-wrap { overflow-x: auto; }
    .order-product-table { min-width: 960px; }
    .order-product-table thead {
        background: #f4f6fa;
        color: #48556a;
    }
    .order-product-table th {
        border: 0 !important;
        font-size: 13px;
        font-weight: 700;
        padding: 15px 10px;
    }
    .order-product-table td {
        border-top: 1px solid #edf0f4;
        vertical-align: middle;
    }
    .pos-summary-table {
        margin-left: auto;
        max-width: 430px;
    }
    .pos-summary-table td {
        border-color: #edf0f4;
        padding: 11px 10px;
    }
    .pos-grand-total {
        color: #008a2e;
        font-size: 18px !important;
        font-weight: 800;
    }
    .btn-pos-primary {
        background: #15c7aa;
        border: none;
        border-radius: 4px;
        color: #fff;
        font-weight: 700;
        padding: 10px 18px;
    }
    .btn-pos-primary:hover { color: #fff; opacity: .94; }
    .increment_btn, .remove_btn {
        margin-top: -17px;
        margin-bottom: 10px;
    }
    .payment-box {
        background: #fbfcfe;
        border: 1px solid #edf0f4;
        border-radius: 8px;
        padding: 18px;
    }
    .payment-box h6 {
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
    }
    .qty-cart .quantity {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .qty-cart .quantity button {
        width: 34px;
        height: 34px;
        line-height: 32px;
        padding: 0;
        border: 1px solid #cbd5f5;
        border-radius: 6px;
        background: #e5edff;
        color: #4f46e5;
        font-weight: 600;
        flex: 0 0 34px;
    }
    .qty-cart .quantity input {
        width: 76px;
        min-width: 76px;
        height: 34px;
        text-align: center;
        border: 1px solid #cbd5f5;
        border-radius: 8px;
        background: #fff;
        font-weight: 600;
        padding: 4px 8px;
        -moz-appearance: textfield;
    }
    .qty-cart .quantity input::-webkit-outer-spin-button,
    .qty-cart .quantity input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .qty-cart .quantity input:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79,70,229,.12);
    }
</style>
<link href="{{asset('public/backEnd')}}/assets/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('public/backEnd')}}/assets/libs/summernote/summernote-lite.min.css" rel="stylesheet" type="text/css" />
@endsection 

@section('content')
<div class="container-fluid order-simple-shell">
    @php
        $rawOrderSource = trim((string) ($order->note ?? ''));
        $orderSourceValue = '';
        $legacyCourierNote = '';

        if ($rawOrderSource !== '' && preg_match('/^Order Source:\s*(.+)$/im', $rawOrderSource, $matches)) {
            $orderSourceValue = trim($matches[1]);
            $legacyCourierNote = trim(preg_replace('/^Order Source:\s*.+$/im', '', $rawOrderSource));
        } elseif ($rawOrderSource !== '') {
            $orderSourceValue = $rawOrderSource;
        }

        $courierNoteValue = trim((string) ($order->order_note ?? ''));
        if ($courierNoteValue === '' && $legacyCourierNote !== '') {
            $courierNoteValue = $legacyCourierNote;
        }

        $subtotal = Cart::instance('pos_shopping')->subtotal();
        $subtotal = str_replace([',','.00'], '', $subtotal);
        $shipping = Session::get('pos_shipping');
        $total_discount = Session::get('pos_discount') + Session::get('product_discount');
        $total = ($subtotal + $shipping) - $total_discount;
        $paidAmount = \App\Models\Payment::where('order_id', $order->id)->sum('amount');
        $advancePaid = 0;
        $dueAmount = $total;

        if ($paidAmount > 0 && $paidAmount < $total) {
            $advancePaid = $paidAmount;
            $dueAmount = $total - $advancePaid;
        }
    @endphp

    <div class="mb-2 d-flex justify-content-between align-items-center">
        <a href="{{ route('admin.orders', 'all') }}" class="order-back-link">
            <i class="fa fa-arrow-left"></i> Back
        </a>
        <form method="get" action="{{route('admin.order.cart_clear')}}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger delete-confirm" title="Clear Cart">
                <i class="fas fa-trash-alt"></i> Cart Clear
            </button>
        </form>
    </div>

    <form action="{{route('admin.order.update')}}" method="POST" class="pos_form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" value="{{$order->id}}" name="order_id">

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="order-simple-card">
                    <h5 class="card-heading">Customer Information</h5>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Customer Phone</label>
                            <input type="number" id="phone" class="form-control" placeholder="Enter customer 11 digit mobile number" name="phone" value="{{$shippinginfo->phone}}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="name" class="form-control" placeholder="Name" name="name" value="{{$shippinginfo->name}}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" id="address" class="form-control" placeholder="address" name="address" value="{{$shippinginfo->address}}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery Area</label>
                            <select id="area" class="form-control" name="area" required>
                                <option value="">Select Delivery Area</option>
                                @foreach($shippingcharge as $key=>$value)
                                    <option value="{{$value->id}}" @if($shippinginfo->area == $value->name) selected @endif>{{$value->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Order Source</label>
                            <select id="order_source" name="order_source" class="form-control">
                                <option value="">Select Source</option>
                                <option value="Web Site" {{ $orderSourceValue === 'Web Site' ? 'selected' : '' }}>Web Site</option>
                                <option value="FB" {{ $orderSourceValue === 'FB' ? 'selected' : '' }}>FB</option>
                                <option value="Whatsapp" {{ $orderSourceValue === 'Whatsapp' ? 'selected' : '' }}>Whatsapp</option>
                                <option value="Landing Page" {{ $orderSourceValue === 'Landing Page' ? 'selected' : '' }}>Landing Page</option>
                                <option value="Messenger" {{ $orderSourceValue === 'Messenger' ? 'selected' : '' }}>Messenger</option>
                                <option value="Phone Call" {{ $orderSourceValue === 'Phone Call' ? 'selected' : '' }}>Phone Call</option>
                                <option value="Reseller" {{ $orderSourceValue === 'Reseller' ? 'selected' : '' }}>Reseller</option>
                                <option value="Imo" {{ $orderSourceValue === 'Imo' ? 'selected' : '' }}>Imo</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Order Status</label>
                            <select id="order_status" name="order_status" class="form-control">
                                @foreach($quickOrderStatuses ?? [] as $status)
                                    <option value="{{ $status->id }}" {{ (int) $order->order_status === (int) $status->id ? 'selected' : '' }}>
                                        {{ strtolower((string) ($status->slug ?? '')) === 'processing' || strtolower((string) ($status->name ?? '')) === 'processing' ? 'Approved' : $status->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Paid Return Amount</label>
                            <input type="number" min="0" step="0.01" name="paid_return_amount" class="form-control" value="{{ old('paid_return_amount', number_format((float) ($order->paid_return_amount ?? 0), 2, '.', '')) }}">
                            <small class="text-muted">Only fill when status is Paid Return.</small>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Note</label>
                            <textarea id="courier_note" class="form-control" name="courier_note" rows="2" placeholder="note">{{ $courierNoteValue }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="order-simple-card">
                    <h5 class="card-heading">Product Information</h5>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Scan Barcode || product code</label>
                            <select id="cart_add" class="form-control select2">
                                <option value="">type product code or name</option>
                                @foreach($products as $value)
                                    <option value="{{$value->id}}">{{$value->name}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="product-table-wrap mb-3">
                            <table class="table order-product-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Product</th>
                                        <th>Color</th>
                                        <th>Size</th>
                                        <th>Quantity</th>
                                        <th>Sell Price</th>
                                        <th>Admin Price</th>
                                        <th>Discount</th>
                                        <th>Sub Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="cartTable">
                                    @include('backEnd.order.cart_table_rows')
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            <table class="table table-sm pos-summary-table mb-0">
                                <tbody id="cart_details">
                                    <tr>
                                        <td>Total Amount</td>
                                        <td class="text-end">{{ $subtotal }}</td>
                                    </tr>
                                    <tr>
                                        <td>Shipping charge</td>
                                        <td class="text-end">
                                            <input type="number" min="0" step="0.01" name="shipping_charge" id="shipping_charge" class="form-control form-control-sm text-end d-inline-block quick-select-input" value="{{ number_format((float) $shipping, 2, '.', '') }}" style="max-width: 140px;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Discount</td>
                                        <td class="text-end">{{ $total_discount }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount due</strong></td>
                                        <td class="text-end pos-grand-total"><strong>{{ $total }}</strong></td>
                                    </tr>
                                    @if($advancePaid > 0)
                                        <tr>
                                            <td><strong>Advance Paid</strong></td>
                                            <td class="text-end"><strong>{{ number_format($advancePaid, 2) }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Due Amount</strong></td>
                                            <td class="text-end"><strong>{{ number_format($dueAmount, 2) }}</strong></td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="payment-box mt-4">
                            <h6><i class="fa fa-credit-card"></i> Payment Info</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Gateway</label>
                                    <input type="text" class="form-control" value="{{ ucfirst($order->payment_gateway ?? 'N/A') }}" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Status</label>
                                    <div class="input-group">
                                        <select id="payment_status_{{ $order->id }}" class="form-select">
                                            <option value="pending" {{ $order->payment_status == 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="paid" {{ $order->payment_status == 'paid' ? 'selected' : '' }}>Paid</option>
                                            <option value="unpaid" {{ $order->payment_status == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                            <option value="failed" {{ $order->payment_status == 'failed' ? 'selected' : '' }}>Failed</option>
                                        </select>
                                        <button type="button" class="btn btn-success" onclick="updatePaymentStatus({{ $order->id }})">
                                            <i class="fa fa-check"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-start mt-3">
                            <button type="submit" class="btn btn-pos-primary">Update Order</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
<script>
function updatePaymentStatus(orderId) {
    let status = document.getElementById('payment_status_' + orderId).value;

    fetch('{{ route("admin.order.updatePaymentStatus") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId, payment_status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            toastr.success(data.message, 'Success!');
        } else {
            toastr.error(data.message, 'Error!');
        }
    })
    .catch(err => {
        toastr.error('Something went wrong!', 'Error!');
    });
}
</script>
@endsection
@section('script')
<script src="{{asset('public/backEnd/')}}/assets/libs/parsleyjs/parsley.min.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/js/pages/form-validation.init.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/libs/select2/js/select2.min.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/js/pages/form-advanced.init.js"></script>
<!-- Plugins js -->
<script src="{{asset('public/backEnd/')}}/assets/libs//summernote/summernote-lite.min.js"></script>
<script>
    $(".summernote").summernote({
        placeholder: "Enter Your Text Here",
    });
</script>

<script type="text/javascript">
    $(document).ready(function () {
        $('.select2').select2();
    });
</script>
<script>
    function syncCartQty(rowId, qty){
        if(!rowId) return;

        $.ajax({
            cache: false,
            type:"GET",
            data:{'id':rowId,'qty':qty},
            url:"{{route('admin.order.cart_set_qty')}}",
            dataType: "json",
            success: function(){
                return cart_content()+cart_details();
            }
        });
    }

    var cartQtyTimers = {};

    function cart_content(){
           $.ajax({
             type:"GET",
             url:"{{route('admin.order.cart_content')}}",
             dataType: "html",
             success: function(cartinfo){
               $('#cartTable').html(cartinfo)
             }
          });
      }
      function cart_details(){
           $.ajax({
             type:"GET",
             url:"{{route('admin.order.cart_details')}}",
             dataType: "html",
             success: function(cartinfo){
               $('#cart_details').html(cartinfo)
             }
          });
      }

      $('#cart_add').on('change',function(e){
       var id =$(this).val();
        if(id){
            $.ajax({
            cache: 'false',
            type:"GET",
            data:{'id':id},
            url:"{{route('admin.order.cart_add')}}",
            dataType: "json",
            success: function(cartinfo){
                return cart_content()+cart_details();
            }
            });
        }
       });
    $(document).on("click", ".cart_increment", function(e){
        e.preventDefault();
        var id = $(this).data("id");
        var qty = $(this).val();
        if(id){
              $.ajax({
               cache: false,
               data:{'id':id,'qty':qty},
               type:"GET",
               url:"{{route('admin.order.cart_increment')}}",
               dataType: "json",
            success: function(cartinfo){
                return cart_content()+cart_details();
            }
          });
        }
   });
    $(document).on("click", ".cart_decrement", function(e){
        e.preventDefault();
        var id = $(this).data("id");
        var qty = $(this).val();
        if(id){
              $.ajax({
               cache: false, 
               type:"GET",
               data:{'id':id,'qty':qty},
               url:"{{route('admin.order.cart_decrement')}}",
               dataType: "json",
            success: function(cartinfo){
                return cart_content()+cart_details();
            }
          });
        }
   });
    $(document).on("click", ".cart_remove", function(e){
        e.preventDefault();
        var id = $(this).data("id");
        if(id){
              $.ajax({
               cache: false,
               type:"GET",
               data:{'id':id},
               url:"{{route('admin.order.cart_remove')}}",
               dataType: "json",
              success: function(cartinfo){
                return cart_content()+cart_details();
            }
          });
        }
   });
   $(document).on("change", ".product_discount", function(){
        var id = $(this).data("id");
        var discount = $(this).val();
          $.ajax({
           cache: false,
           type:"GET",
           data:{'id':id,'discount':discount},
           url:"{{route('admin.order.product_discount')}}",
           dataType: "json",
          success: function(cartinfo){
            return cart_content()+cart_details();
          }
        });
   });
   $(document).on("input", ".cart_qty_input", function(){
        this.value = this.value.replace(/[^\d]/g, '');
        var $input = $(this);
        var id = $input.data("id");
        var qty = parseInt($input.val(), 10);

        if (cartQtyTimers[id]) {
            clearTimeout(cartQtyTimers[id]);
        }

        if (!id || isNaN(qty) || qty < 1) {
            return;
        }

        cartQtyTimers[id] = setTimeout(function(){
            syncCartQty(id, qty);
        }, 500);
   });
   $(document).on("change", ".cart_qty_input", function(){
        var id = $(this).data("id");
        var qty = parseInt($(this).val(), 10);
        qty = isNaN(qty) || qty < 1 ? 1 : qty;
        $(this).val(qty);

        if (cartQtyTimers[id]) {
            clearTimeout(cartQtyTimers[id]);
        }

        syncCartQty(id, qty);
   });
   $(document).on("focus click", ".quick-select-input", function(){
        $(this).select();
   });
   $(document).on("mousedown", ".quick-select-input", function(e){
        var input = this;
        if (document.activeElement !== input) {
            e.preventDefault();
            $(input).trigger("focus");
            setTimeout(function(){
                input.select();
            }, 0);
        }
   });
   $(document).on("keydown", ".cart_qty_input", function(e){
        if (e.key === "Enter") {
            e.preventDefault();
            $(this).trigger("change");
        }
   });
   $(document).on("change", ".sell_price_input", function(){
        var id = $(this).data("id");
        var price = $(this).val();
          $.ajax({
           cache: false,
           type:"GET",
           data:{'id':id,'price':price},
           url:"{{route('admin.order.cart_sell_price')}}",
           dataType: "json",
          success: function(cartinfo){
            return cart_content()+cart_details();
          }
        });
   });
   $(document).on("change", ".admin_price_input", function(){
        var id = $(this).data("id");
        var price = $(this).val();
          $.ajax({
           cache: false,
           type:"GET",
           data:{'id':id,'price':price},
           url:"{{route('admin.order.cart_admin_price')}}",
           dataType: "json",
          success: function(cartinfo){
            return cart_content()+cart_details();
          }
        });
   });
    $(".cartclear").click(function(e){
      $.ajax({
           cache: false,
           type:"GET",
           url:"{{route('admin.order.cart_clear')}}",
           dataType: "json",
          success: function(cartinfo){
            return cart_content()+cart_details();
          }
       });
   });// pshippingfee from total
     $("#area").on("change", function () {
         var id = $(this).val();
         $.ajax({
             type: "GET",
             data: { id: id },
            url: "{{route('admin.order.cart_shipping')}}",
            dataType: "html",
            success: function(cartinfo){
               return cart_content()+cart_details();
              }
          });
      });
     $(document).on("change keyup", "#shipping_charge", function () {
         var amount = $(this).val();
         $.ajax({
             type: "GET",
             data: { amount_manual: amount },
             url: "{{route('admin.order.cart_shipping')}}",
             dataType: "json",
             success: function () {
                 cart_details();
             }
         });
     });
  // Event listener for size selector change
  $(document).on('change', '.cart-size-selector', function() {
    var rowId = $(this).data('id'); // Get the row ID
    var selectedSize = $(this).val(); // Get the selected size
     $.ajax({
           cache: false,
           type:"GET",
           data:{'id':rowId,'product_size':selectedSize},
           url:"{{ route('admin.order.cart.update') }}",
           dataType: "json",
            success: function(cartinfo){
            return cart_content()+cart_details();
          }
        });

});


// Event listener for color selector change
$(document).on('change', '.cart-color-selector', function() {
    var rowId = $(this).data('id'); // Get the row ID
    var selectedColor = $(this).val(); // Get the selected color
    $.ajax({
           cache: false,
           type:"GET",
           data:{'id':rowId,'product_color':selectedColor},
           url:"{{ route('admin.order.cart.update') }}",
           dataType: "json",
            success: function(cartinfo){
            return cart_content()+cart_details();
          }
        });

});
</script>
@endsection

