@extends('backEnd.layouts.master')
@section('title','Point of Sale')

@section('css')
<style>
    body{
        background:#eef1f8;
    }
    .pos-shell{
        background:#eef1f8;
        padding:10px 0 25px;
    }
    .pos-card{
        background:#ffffff;
        border-radius:14px;
        box-shadow:0 15px 30px rgba(15,23,42,0.08);
        padding:14px 14px 10px;
        border:1px solid rgba(148,163,184,0.25);
    }
    .pos-header-bar{
        background:linear-gradient(135deg,#4f46e5,#6366f1);
        color:#fff;
        border-radius:12px;
        padding:10px 14px;
        margin-bottom:10px;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    .pos-header-bar h5{
        margin:0;
        font-size:16px;
        font-weight:600;
        letter-spacing:.3px;
    }
    .pos-badge-soft{
        padding:3px 10px;
        border-radius:999px;
        background:rgba(15,23,42,.18);
        font-size:12px;
    }

    /* LEFT – CART TABLE */
    .pos-cart-table thead{
        background:#f9fafb;
    }
    .pos-cart-table th{
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:.03em;
        color:#64748b;
        border-bottom:1px solid #e2e8f0;
    }
    .pos-cart-table td{
        vertical-align:middle;
    }
    .pos-cart-table th:nth-child(2),
    .pos-cart-table td:nth-child(2){
        min-width:220px;
    }

    .qty-cart .quantity{
        display:flex;
        align-items:center;
        justify-content:center;
        gap:8px;
    }
    .qty-cart .quantity button{
        border:1px solid #cbd5f5;
        background:#e5edff;
        width:34px;
        height:34px;
        border-radius:6px;
        line-height:32px;
        text-align:center;
        padding:0;
        font-weight:600;
        color:#4f46e5;
        flex:0 0 34px;
    }
    .qty-cart .quantity input{
        width:76px;
        min-width:76px;
        height:34px;
        text-align:center;
        border:1px solid #cbd5f5;
        border-radius:8px;
        background:#fff;
        font-weight:600;
        padding:4px 8px;
        -moz-appearance:textfield;
    }
    .qty-cart .quantity input::-webkit-outer-spin-button,
    .qty-cart .quantity input::-webkit-inner-spin-button{
        -webkit-appearance:none;
        margin:0;
    }
    .qty-cart .quantity input:focus{
        outline:none;
        border-color:#4f46e5;
        box-shadow:0 0 0 3px rgba(79,70,229,.12);
    }

    /* CUSTOMER + TOTAL CARD */
    .pos-section-title{
        font-size:13px;
        text-transform:uppercase;
        letter-spacing:.12em;
        color:#94a3b8;
        font-weight:600;
        margin-bottom:6px;
    }
    .pos-summary-table td{
        padding:6px 10px;
        font-size:14px;
    }
    .pos-summary-table tr:last-child td{
        border-top:1px dashed #e2e8f0;
        font-size:15px;
        font-weight:700;
    }
    .pos-grand-total{
        font-size:18px !important;
        color:#16a34a;
    }

    .btn-pos-primary{
        background:linear-gradient(135deg,#4f46e5,#6366f1);
        border:none;
        padding:9px 22px;
        border-radius:999px;
        font-weight:600;
        font-size:14px;
        box-shadow:0 10px 20px rgba(79,70,229,.35);
        color:#fff;
    }
    .btn-pos-primary:hover{
        opacity:.94;
        box-shadow:0 16px 30px rgba(79,70,229,.45);
    }

    /* RIGHT – PRODUCTS GRID */
    .pos-products-wrapper{
        max-height:520px;
        overflow-y:auto;
        padding-right:4px;
    }
    .pos-product-card{
        border-radius:12px;
        padding:8px 8px 10px;
        margin-bottom:10px;
        text-align:center;
        cursor:pointer;
        transition:.18s all;
        background:linear-gradient(145deg,#f9fafb,#e5edff);
        border:1px solid rgba(148,163,184,.35);
        position:relative;
    }
    .pos-product-card:hover{
        transform:translateY(-2px);
        box-shadow:0 12px 24px rgba(15,23,42,.15);
    }
    .pos-product-img{
        height:72px;
        object-fit:contain;
        margin-bottom:4px;
    }
    .pos-product-name{
        font-size:13px;
        font-weight:600;
        min-height:34px;
        color:#111827;
    }
    .pos-product-price{
        font-size:14px;
        font-weight:700;
        color:#16a34a;
    }
    .pos-stock-badge{
        position:absolute;
        top:6px;
        left:8px;
        background:rgba(30,64,175,.12);
        color:#1d4ed8;
        font-size:11px;
        padding:2px 6px;
        border-radius:999px;
    }

    .pos-search-bar input{
        border-radius:999px;
        border:1px solid #cbd5f5;
        font-size:13px;
        padding-left:32px;
    }
    .pos-search-bar .icon{
        position:absolute;
        top:50%;
        left:10px;
        transform:translateY(-50%);
        color:#94a3b8;
        font-size:13px;
    }
    .pos-autocomplete{
        position:relative;
    }
    .pos-suggestion-list{
        position:absolute;
        top:calc(100% + 6px);
        left:0;
        right:0;
        z-index:1050;
        background:#fff;
        border:1px solid #dbe3f3;
        border-radius:12px;
        box-shadow:0 18px 35px rgba(15,23,42,.12);
        overflow:hidden;
        display:none;
        max-height:320px;
        overflow-y:auto;
    }
    .pos-suggestion-item{
        padding:10px 14px;
        border-bottom:1px solid #eef2f7;
        cursor:pointer;
        transition:background .15s ease;
    }
    .pos-suggestion-item:last-child{
        border-bottom:none;
    }
    .pos-suggestion-item:hover,
    .pos-suggestion-item.active{
        background:#eef4ff;
    }
    .pos-suggestion-name{
        font-size:14px;
        font-weight:600;
        color:#111827;
    }
    .pos-suggestion-meta{
        font-size:12px;
        color:#64748b;
    }
    .pos-suggestion-empty{
        padding:12px 14px;
        font-size:13px;
        color:#94a3b8;
    }
</style>
<link href="{{asset('public/backEnd')}}/assets/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('public/backEnd')}}/assets/libs/summernote/summernote-lite.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="container-fluid pos-shell">

    {{-- TOP BAR --}}
    <div class="row mb-2">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Point of Sale</h4>
                <form method="get" action="{{route('admin.order.cart_clear')}}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill delete-confirm" title="Clear Cart">
                        <i class="fas fa-trash-alt"></i> Cart Clear
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ================= LEFT COLUMN ================= --}}
        <div class="col-lg-12">
            <div class="pos-card h-100">

                {{-- POS HEADER STRIP --}}
                <div class="pos-header-bar mb-3">
                    <div>
                        <h5>Shop Store</h5>
                        <small class="pos-badge-soft">Walk-in Customer POS</small>
                    </div>
                    <div class="text-end">
                        <div style="font-size:12px;opacity:.8;">Session</div>
                        <div style="font-weight:600;">SL-{{ date('dmy-His') }}</div>
                    </div>
                </div>

                {{-- CUSTOMER + TOTAL --}}
                <form action="{{route('admin.order.store')}}" method="POST" class="row pos_form" data-parsley-validate="" enctype="multipart/form-data" id="pos_order_form">
                    @csrf
                    <input type="hidden" name="coupon_code" value="{{ Session::get('pos_coupon_code', '') }}">

                    {{-- CUSTOMER --}}
                 
<div class="row mt-3">

    <!-- LEFT SIDE -->
    <div class="col-md-4">
        <div class="pos-card p-3">
            <h5 class="mb-3">Customer Information</h5>

            <input type="text" name="phone" class="form-control mb-2" placeholder="Customer Phone">

            <input type="text" name="name" class="form-control mb-2" placeholder="Name">

            <input type="text" name="address" class="form-control mb-2" placeholder="Address">

            <select name="area" class="form-control mb-2">
                <option>Select Delivery Area</option>
                @foreach($shippingcharge ?? [] as $area)
                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                @endforeach
            </select>

            <select name="order_source" class="form-control mb-2">
                <option value="">Select Source</option>
                <option value="Web Site">Web Site</option>
                <option value="FB">FB</option>
                <option value="Whatsapp">Whatsapp</option>
                <option value="Landing Page">Landing Page</option>
                <option value="Messenger">Messenger</option>
                <option value="Phone Call">Phone Call</option>
                <option value="Reseller">Reseller</option>
                <option value="Imo">Imo</option>
            </select>

            <textarea name="note" class="form-control" placeholder="Note"></textarea>
        </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="col-md-8">
        <div class="pos-card p-3">
            <h5 class="mb-3">Product Information</h5>

            <div class="pos-autocomplete mb-3">
                <input type="text" id="product_search" class="form-control" placeholder="Scan Barcode | product code | product name">
                <div id="product_suggestion_list" class="pos-suggestion-list"></div>
            </div>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Sell Price</th>
                        <th>Admin Price</th>
                        <th>Discount</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="cartTable">
                    @include('backEnd.order.cart_table_rows_pos')
                </tbody>
            </table>

            <!-- SUMMARY -->
            <div class="row align-items-end mt-3">
                <div class="col-md-6">
                    <table class="table table-sm pos-summary-table mb-0">
                        <tbody id="cart_details">
                            @include('backEnd.order.cart_details')
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6 text-end mt-3 mt-md-0">
                    <button type="submit" class="btn btn-pos-primary">
                        Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
                    {{-- SUMMARY --}}
                   
        {{-- ================= RIGHT COLUMN – PRODUCT LIST ================= --}}
        

                {{-- SEARCH BAR --}}
                <div class="mb-2">
                   
                    <div class="pos-search-bar position-relative">
                        <span class="icon"><i class="fa fa-search"></i></span>
                        <input type="text"
                               id="product_filter"
                               class="form-control form-control-sm"
                               placeholder="Search product by name...">
                    </div>
                </div>

@endsection

@section('script')
<script src="{{asset('public/backEnd/')}}/assets/libs/parsleyjs/parsley.min.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/js/pages/form-validation.init.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/libs/select2/js/select2.min.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/js/pages/form-advanced.init.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/libs//summernote/summernote-lite.min.js"></script>

<script>
    $(".summernote").summernote({
        placeholder: "Enter Your Text Here",
    });
</script>

<script type="text/javascript">
    $(document).ready(function () {
        $(".select2").select2();
    });

    function syncCartQty(rowId, qty) {
        if (!rowId) return;

        $.ajax({
            cache: false,
            type: "GET",
            data: { id: rowId, qty: qty },
            url: "{{route('admin.order.cart_set_qty')}}",
            dataType: "json",
            success: function () {
                cart_content();
                cart_details();
            },
        });
    }

    var cartQtyTimers = {};

    // -------- CART CONTENT LOADERS ----------
    function cart_content() {
        $.ajax({
            type: "GET",
            url: "{{route('admin.order.cart_content')}}",
            data: { mode: "pos" },
            dataType: "html",
            success: function (cartinfo) {
                $("#cartTable").html(cartinfo);
            },
        });
    }
    function cart_details() {
        $.ajax({
            type: "GET",
            url: "{{route('admin.order.cart_details')}}",
            dataType: "html",
            success: function (cartinfo) {
                $("#cart_details").html(cartinfo);
            },
        });
    }

    // -------- PRODUCT CLICK -> ADD TO CART ----------
    $(document).on("click", ".pos-add-product", function (e) {
        e.preventDefault();
        var id = $(this).data("id");
        if (id) {
            $.ajax({
                cache: false,
                type: "GET",
                data: { id: id },
                url: "{{route('admin.order.cart_add')}}",
                dataType: "json",
                success: function (cartinfo) {
                    cart_content();
                    cart_details();
                },
            });
        }
    });

    // -------- CART QTY + / - (Delegated) ----------
    $(document).on("click", ".cart_increment", function (e) {
        e.preventDefault();
        var id = $(this).data("id");
        var qty = $(this).val();
        if (id) {
            $.ajax({
                cache: false,
                data: { id: id, qty: qty },
                type: "GET",
                url: "{{route('admin.order.cart_increment')}}",
                dataType: "json",
                success: function (cartinfo) {
                    cart_content();
                    cart_details();
                },
            });
        }
    });

    $(document).on("click", ".cart_decrement", function (e) {
        e.preventDefault();
        var id = $(this).data("id");
        var qty = $(this).val();
        if (id) {
            $.ajax({
                cache: false,
                type: "GET",
                data: { id: id, qty: qty },
                url: "{{route('admin.order.cart_decrement')}}",
                dataType: "json",
                success: function (cartinfo) {
                    cart_content();
                    cart_details();
                },
            });
        }
    });

    // -------- CART REMOVE ----------
    $(document).on("click", ".cart_remove", function (e) {
        e.preventDefault();
        var id = $(this).data("id");
        if (id) {
            $.ajax({
                cache: false,
                type: "GET",
                data: { id: id },
                url: "{{route('admin.order.cart_remove')}}",
                dataType: "json",
                success: function (cartinfo) {
                    cart_content();
                    cart_details();
                },
            });
        }
    });

    $(document).on("change", ".product_discount", function () {
        var id = $(this).data("id");
        var discount = $(this).val();

        if (!id) return;

        $.ajax({
            cache: false,
            type: "GET",
            data: { id: id, discount: discount },
            url: "{{route('admin.order.product_discount')}}",
            dataType: "json",
            success: function () {
                cart_content();
                cart_details();
            },
        });
    });

    $(document).on("change", ".sell_price_input", function () {
        var id = $(this).data("id");
        var price = $(this).val();

        if (!id) return;

        $.ajax({
            cache: false,
            type: "GET",
            data: { id: id, price: price },
            url: "{{route('admin.order.cart_sell_price')}}",
            dataType: "json",
            success: function () {
                cart_content();
                cart_details();
            },
        });
    });

    $(document).on("change", ".admin_price_input", function () {
        var id = $(this).data("id");
        var price = $(this).val();

        if (!id) return;

        $.ajax({
            cache: false,
            type: "GET",
            data: { id: id, price: price },
            url: "{{route('admin.order.cart_admin_price')}}",
            dataType: "json",
            success: function () {
                cart_content();
                cart_details();
            },
        });
    });

    // -------- COUPON APPLY ----------
    $("#pos_apply_coupon").on("click", function () {
        var code = $("#pos_coupon_code").val().trim();
        if (!code) {
            $("#pos_coupon_msg").removeClass("text-success").addClass("text-danger").text("কুপন কোড লিখুন");
            return;
        }
        $.ajax({
            type: "POST",
            url: "{{ route('admin.order.pos.apply_coupon') }}",
            data: { _token: "{{ csrf_token() }}", coupon_code: code },
            dataType: "json",
            success: function (res) {
                if (res.success) {
                    $("#pos_coupon_msg").removeClass("text-danger").addClass("text-success").text(res.message);
                    $("#pos_remove_coupon").show();
                    cart_details();
                } else {
                    $("#pos_coupon_msg").removeClass("text-success").addClass("text-danger").text(res.message || "কুপন বৈধ নয়");
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "ত্রুটি হয়েছে";
                $("#pos_coupon_msg").removeClass("text-success").addClass("text-danger").text(msg);
            }
        });
    });

    // -------- COUPON REMOVE ----------
    $("#pos_remove_coupon").on("click", function () {
        $.ajax({
            type: "GET",
            url: "{{ route('admin.order.pos.remove_coupon') }}",
            dataType: "json",
            success: function () {
                $("#pos_coupon_code").val("");
                $("#pos_coupon_msg").text("");
                $("#pos_remove_coupon").hide();
                cart_details();
            }
        });
    });

    // -------- SHIPPING CHANGE ----------
    $(document).on("change", "#area", function () {
        var id = $(this).val();
        $.ajax({
            type: "GET",
            data: { id: id },
            url: "{{route('admin.order.cart_shipping')}}",
            dataType: "json",
            success: function () {
                cart_content();
                cart_details();
            },
        });
    });

    // -------- SIZE / COLOR SELECT (variant price update) ----------
    function updateCartVariant(rowId, productId, sizeId, colorId) {
        var $row = $('.cart-size-selector[data-id="'+rowId+'"]').closest('tr');
        if (!$row.length) $row = $('.cart-color-selector[data-id="'+rowId+'"]').closest('tr');
        var $sizeSelect = $row.find('.cart-size-selector');
        var $colorSelect = $row.find('.cart-color-selector');
        var sId = sizeId !== undefined ? sizeId : ($sizeSelect.length ? $sizeSelect.val() : '');
        var cId = colorId !== undefined ? colorId : ($colorSelect.length ? $colorSelect.val() : '');
        var pid = productId || $row.find('.cart-size-selector, .cart-color-selector').first().data('product-id') || '';
        $.ajax({
            cache: false,
            type: "GET",
            data: { id: rowId, product_id: pid, size_id: sId || '', color_id: cId || '' },
            url: "{{ route('admin.order.cart.update') }}",
            dataType: "json",
            success: function () {
                cart_content();
                cart_details();
            },
        });
    }
    $(document).on("change", ".cart-size-selector", function () {
        var rowId = $(this).data("id");
        var productId = $(this).data("product-id");
        var sizeId = $(this).val();
        updateCartVariant(rowId, productId, sizeId, undefined);
    });
    $(document).on("change", ".cart-color-selector", function () {
        var rowId = $(this).data("id");
        var productId = $(this).data("product-id");
        var colorId = $(this).val();
        updateCartVariant(rowId, productId, undefined, colorId);
    });

    // -------- FORM SUBMIT - আগে Size/Color সিঙ্ক করুন --------
    var posFormSubmitting = false;
    $("#pos_order_form").on("submit", function (e) {
        if (posFormSubmitting) return;
        e.preventDefault();
        var form = this;
        var rows = [];
        $(".cart-size-selector, .cart-color-selector").each(function () {
            var rowId = $(this).data("id");
            if (rowId && rows.indexOf(rowId) === -1) rows.push(rowId);
        });
        if (rows.length === 0) {
            posFormSubmitting = true;
            form.submit();
            return;
        }
        var promises = [];
        rows.forEach(function (rowId) {
            var $row = $('.cart-size-selector[data-id="'+rowId+'"]').closest('tr');
            if (!$row.length) $row = $('.cart-color-selector[data-id="'+rowId+'"]').closest('tr');
            var sId = $row.find('.cart-size-selector').val() || '';
            var cId = $row.find('.cart-color-selector').val() || '';
            var productId = $row.find('.cart-size-selector, .cart-color-selector').first().data('product-id') || '';
            promises.push($.ajax({
                type: "GET",
                url: "{{ route('admin.order.cart.update') }}",
                data: { id: rowId, product_id: productId, size_id: sId, color_id: cId },
                dataType: "json"
            }));
        });
        $.when.apply($, promises).always(function () {
            posFormSubmitting = true;
            setTimeout(function () { form.submit(); }, 150);
        });
    });

    function addProductToCart(productId) {
        if (!productId) return;

        $.ajax({
            url: "{{route('admin.order.cart_add')}}",
            type: "GET",
            data: { id: productId },
            success: function(){
                cart_content();
                cart_details();
            }
        });
    }

    function hideProductSuggestions() {
        $("#product_suggestion_list").hide().empty();
        $("#product_search").data("active-index", -1);
    }

    function renderProductSuggestions(items) {
        var $list = $("#product_suggestion_list");

        if (!items.length) {
            $list.html('<div class="pos-suggestion-empty">No matching product found</div>').show();
            return;
        }

        var html = "";
        items.forEach(function(item, index) {
            html += '<div class="pos-suggestion-item' + (index === 0 ? ' active' : '') + '" data-id="' + item.id + '">' +
                        '<div class="pos-suggestion-name">' + (item.name || 'Unnamed Product') + '</div>' +
                        '<div class="pos-suggestion-meta">Code: ' + (item.product_code || 'N/A') + ' | Price: ' + (item.price || 0) + ' | Stock: ' + (item.stock || 0) + '</div>' +
                    '</div>';
        });

        $list.html(html).show();
        $("#product_search").data("active-index", items.length ? 0 : -1);
    }

    function fetchProductSuggestions(term) {
        $.ajax({
            url: "{{ route('admin.order.product.search') }}",
            type: "GET",
            dataType: "json",
            data: { name: term, suggest: 1 },
            success: function(res) {
                renderProductSuggestions(Array.isArray(res) ? res : []);
            },
            error: function() {
                hideProductSuggestions();
            }
        });
    }

    var suggestionTimer = null;

    $("#product_search").on("input", function() {
        var term = $(this).val().trim();

        clearTimeout(suggestionTimer);

        if (term.length < 2) {
            hideProductSuggestions();
            return;
        }

        suggestionTimer = setTimeout(function() {
            fetchProductSuggestions(term);
        }, 180);
    });

    $("#product_search").on("keydown", function(e){
        var $items = $("#product_suggestion_list .pos-suggestion-item");
        var activeIndex = parseInt($(this).data("active-index"), 10);

        if (e.which === 40 && $items.length) {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, $items.length - 1);
            $items.removeClass("active").eq(activeIndex).addClass("active");
            $(this).data("active-index", activeIndex);
            return;
        }

        if (e.which === 38 && $items.length) {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            $items.removeClass("active").eq(activeIndex).addClass("active");
            $(this).data("active-index", activeIndex);
            return;
        }

        if (e.which === 13){
            e.preventDefault();

            if ($items.length && activeIndex >= 0) {
                $items.eq(activeIndex).trigger("click");
                return;
            }

            let name = $(this).val().trim();
            if (!name) return;

            $.ajax({
                url: "{{ route('admin.order.product.search') }}",
                type: "GET",
                data: { name: name },
                success: function(res){
                    if(res.id){
                        addProductToCart(res.id);
                    }
                },
                complete: function() {
                    $("#product_search").val('');
                    hideProductSuggestions();
                }
            });
        }
    });

    $(document).on("input", ".cart_qty_input", function () {
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

        cartQtyTimers[id] = setTimeout(function () {
            syncCartQty(id, qty);
        }, 500);
    });

    $(document).on("change", ".cart_qty_input", function () {
        var id = $(this).data("id");
        var qty = parseInt($(this).val(), 10);

        qty = isNaN(qty) || qty < 1 ? 1 : qty;
        $(this).val(qty);

        if (cartQtyTimers[id]) {
            clearTimeout(cartQtyTimers[id]);
        }

        syncCartQty(id, qty);
    });

    $(document).on("focus click", ".cart_qty_input", function () {
        $(this).select();
    });

    $(document).on("keydown", ".cart_qty_input", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            $(this).trigger("change");
        }
    });

    $(document).on("click", ".pos-suggestion-item", function() {
        var productId = $(this).data("id");
        addProductToCart(productId);
        $("#product_search").val('').focus();
        hideProductSuggestions();
    });

    $(document).on("click", function(e) {
        if (!$(e.target).closest(".pos-autocomplete").length) {
            hideProductSuggestions();
        }
    });
</script>
@endsection
