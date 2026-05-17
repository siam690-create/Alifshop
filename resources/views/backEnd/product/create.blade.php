@extends('backEnd.layouts.master')
@section('title','Create New Product')

@section('css')
<link href="{{asset('public/backEnd')}}/assets/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('public/backEnd')}}/assets/libs/summernote/summernote-lite.min.css" rel="stylesheet" type="text/css" />
<style>
    /* কাস্টম ডিজাইন */
    .section-title { background: #f1f3f7; padding: 10px 15px; border-radius: 6px; font-weight: 700; color: #343a40; border-left: 4px solid #727cf5; margin-bottom: 20px; font-size: 15px; }
    .form-label { font-weight: 600; font-size: 13px; color: #555; }
    .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0.75rem; }
    
    /* ভ্যারিয়েন্ট আইটেম ডিজাইন */
    .variant-card { background: #fafbfd; border: 1px solid #e2e7f1; padding: 15px; border-radius: 10px; margin-bottom: 12px; position: relative; }
    
    /* টগল সুইচ */
    .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px; }
    .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #0acf97; }
    input:checked + .slider:before { transform: translateX(20px); }

    .btn-remove-row { margin-top: 28px; }
</style>
@endsection 

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between py-3">
                <h4 class="page-title mb-0">Add New Product</h4>
                <div class="page-title-right">
                    <a href="{{route('products.index')}}" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fe-list me-1"></i> Manage Products</a>
                </div>
            </div>
        </div>
    </div>

    <form action="{{route('products.store')}}" method="POST" data-parsley-validate="" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="section-title"><i class="fe-info me-1"></i> Basic Information</div>
                        
                        <div class="form-group mb-3">
                            <label for="name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Enter product name" required />
                            @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Main Category *</label>
                                <select class="form-control select2" name="category_id" id="category_id" required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{$category->id}}">{{$category->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sub Category</label>
                                <select class="form-control select2" name="subcategory_id" id="subcategory_id">
                                    <option value="">Choose Sub Category</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Child Category</label>
                                <select class="form-control select2" name="childcategory_id" id="childcategory_id">
                                    <option value="">Choose Child Category</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Full Description *</label>
                            <textarea name="description" class="summernote" required>{{ old('description') }}</textarea>
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label">Short Note</label>
                            <textarea name="note" rows="2" class="form-control" placeholder="Small note for internal use..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="d-block form-label">Wholesale Product</label>
                            <label class="switch"><input type="checkbox" value="1" name="is_wholesale" id="is_wholesale"><span class="slider round"></span></label>
                        </div>
                    </div>
                </div>

                <div id="wholesale_area" style="display:none;" class="card mb-4">
                    <div class="card-body">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span><i class="fe-dollar-sign me-1"></i> Wholesale Pricing Tiers</span>
                            <button type="button" class="btn btn-sm btn-success add-wholesale-tier rounded-pill px-3"><i class="fa fa-plus me-1"></i> Add New Tier</button>
                        </div>
                        
                        <div id="wholesale-wrapper">
                            <div class="variant-card">
                                <div class="row align-items-end">
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Min Quantity</label>
                                        <input type="number" name="wholesale_price[0][min_quantity]" class="form-control" placeholder="e.g. 10">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Max Quantity</label>
                                        <input type="number" name="wholesale_price[0][max_quantity]" class="form-control" placeholder="e.g. 50 (optional)">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Wholesale Price</label>
                                        <input type="number" step="0.01" name="wholesale_price[0][wholesale_price]" class="form-control" placeholder="0.00">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Stock Qty</label>
                                        <input type="number" name="wholesale_price[0][stock]" class="form-control" placeholder="0">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <button type="button" class="btn btn-success add-wholesale-tier w-100"><i class="fa fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4" id="variant_section">
                    <div class="card-body">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span><i class="fe-layers me-1"></i> Product Variants (Size & Color)</span>
                            <button type="button" class="btn btn-sm btn-success add-variant rounded-pill px-3"><i class="fa fa-plus me-1"></i> Add New Variant</button>
                        </div>
                        
                        <div id="variant-wrapper">
                            <div class="variant-card variant-item">
                                <div class="row align-items-end">
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Color <small class="text-muted">(Optional)</small></label>
                                        <select name="variant_price[0][color_id]" class="form-control select2 variant-color-select">
                                            <option value="">Select Color (Optional)</option>
                                            @foreach($colors as $color)
                                                <option value="{{ $color->id }}">{{ $color->colorName ?? $color->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Size <small class="text-muted">(Optional)</small></label>
                                        <select name="variant_price[0][size_id][]" class="form-control select2 variant-size-select" multiple>
                                            @foreach($sizes as $size)
                                                <option value="{{ $size->id }}">{{ $size->sizeName ?? $size->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Price <small class="text-muted">(Optional)</small></label>
                                        <input type="number" step="0.01" name="variant_price[0][price]" class="form-control" placeholder="0.00">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">Stock Qty <small class="text-muted">(Optional)</small></label>
                                        <input type="number" name="variant_price[0][stock]" class="form-control" placeholder="0">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <button type="button" class="btn btn-danger btn-remove-row d-none w-100"><i class="fa fa-trash"></i></button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <small class="text-muted">
                                            <i class="fa fa-info-circle"></i> 
                                            আপনি শুধু Color, শুধু Size, অথবা Color + Size উভয় add করতে পারবেন
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="section-title"><i class="fe-search me-1"></i> SEO Configuration</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Meta Title</label>
                                <input type="text" name="meta_title" class="form-control" placeholder="SEO optimized title">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Meta Keywords</label>
                                <input type="text" name="meta_keywords" class="form-control" placeholder="keyword1, keyword2">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" class="form-control" rows="2" placeholder="Brief description for search engines"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Meta Image</label>
                                <input type="file" name="meta_image" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="section-title"><i class="fe-dollar-sign me-1"></i> Pricing & Inventory</div>
                        
                        <div class="form-group mb-3">
                            <label class="form-label">Purchase Price <small class="text-muted">(Optional)</small></label>
                            <input type="number" name="purchase_price" class="form-control border-primary" placeholder="0">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Old Price</label>
                                <input type="number" name="old_price" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Price <small class="text-muted">(Optional)</small></label>
                                <input type="number" name="new_price" class="form-control font-weight-bold" placeholder="0">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Reseller Price</label>
                            <input type="number" step="0.01" name="reseller_price" class="form-control" placeholder="Reseller price (optional)">
                            <small class="text-muted">Special price for resellers. Leave empty if not applicable.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Stock <small class="text-muted">(Optional)</small></label>
                                <input type="number" name="stock" class="form-control" placeholder="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit (kg/pc)</label>
                                <input type="text" name="pro_unit" class="form-control" placeholder="e.g. pcs">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Custom Product Code <small class="text-muted">(Optional)</small></label>
                            <input type="text" name="custom_product_code" class="form-control @error('custom_product_code') is-invalid @enderror" value="{{ old('custom_product_code') }}" placeholder="Leave blank for auto code">
                            <small class="text-muted">If left empty, the website will generate the product code automatically.</small>
                            @error('custom_product_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="section-title"><i class="fe-image me-1"></i> Media & Video</div>
                        
                        <div class="form-group mb-3">
                            <label class="form-label">Product Gallery Images *</label>
                            <div class="increment-wrapper">
                                <div class="input-group control-group increment mb-2">
                                    <input type="file" name="image[]" class="form-control" required>
                                    <button class="btn btn-success btn-increment" type="button"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            <div class="clone d-none">
                                <div class="input-group control-group mt-2">
                                    <input type="file" name="image[]" class="form-control">
                                    <button class="btn btn-danger btn-remove-image" type="button"><i class="fa fa-trash"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Video URL (YT/Vimeo)</label>
                            <input type="text" name="pro_video" class="form-control" placeholder="URL link">
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="section-title"><i class="fe-settings me-1"></i> Product Settings</div>
                        
                        <div class="form-group mb-3">
                            <label class="form-label">Product Type</label>
                            <select class="form-control bg-light" id="product_type" name="product_type">
                                <option value="physical" selected>Physical Product</option>
                                <option value="digital">Digital Product</option>
                            </select>
                        </div>

                        <div class="form-group mb-3" id="advance_area">
                            <label class="form-label">Advance Payment Amount</label>
                            <input type="number" name="advance_amount" class="form-control" placeholder="0.00">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label">Free Delivery</label>
                            <div class="d-flex align-items-center">
                                <label class="switch me-3">
                                    <input type="checkbox" value="1" name="free_delivery">
                                    <span class="slider round"></span>
                                </label>
                                <small class="text-muted">Enable free delivery for this product (No shipping charge will be applied)</small>
                            </div>
                        </div>

                        <div id="digital_area" style="display:none;" class="p-2 border rounded mb-3 bg-light">
                            <label class="form-label">Digital File</label>
                            <input type="file" class="form-control mb-2" name="digital_file">
                            <div class="row">
                                <div class="col-6">
                                    <small>Limit</small>
                                    <input type="number" class="form-control form-control-sm" name="download_limit" value="5">
                                </div>
                                <div class="col-6">
                                    <small>Days Exp.</small>
                                    <input type="number" class="form-control form-control-sm" name="download_expire_days" value="7">
                                </div>
                            </div>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-6 mb-2">
                                <label class="d-block form-label">Status</label>
                                <label class="switch"><input type="checkbox" value="1" name="status" checked><span class="slider round"></span></label>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="d-block form-label">Hot Deal</label>
                                <label class="switch"><input type="checkbox" value="1" name="topsale"><span class="slider round"></span></label>
                            </div>
                            <div class="col-6">
                                <label class="d-block form-label">Flash Sale</label>
                                <label class="switch"><input type="checkbox" value="1" name="flashsale"><span class="slider round"></span></label>
                            </div>
                             <div class="col-6">
                                <label class="form-label">Brand</label>
                                <select class="form-control select2" name="brand_id">
                                    <option value="">None</option>
                                    @foreach($brands as $value)
                                        <option value="{{$value->id}}">{{$value->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 shadow rounded-pill"><i class="fe-check-circle me-1"></i> Publish Product</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection 

@section('script')
<script src="{{asset('public/backEnd/')}}/assets/libs/parsleyjs/parsley.min.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/libs/select2/js/select2.min.js"></script>
<script src="{{asset('public/backEnd/')}}/assets/libs/summernote/summernote-lite.min.js"></script>

<script>
    $(document).ready(function () {
        $('.select2').select2({ width: '100%' });
        $(".summernote").summernote({ height: 200, placeholder: "Describe your product..." });

        // Image Increment
        $(".btn-increment").click(function () {
            var html = $(".clone").html();
            $(".increment-wrapper").append(html);
        });
        $("body").on("click", ".btn-remove-image", function () {
            $(this).parents(".control-group").remove();
        });

        // Product Type Toggle
        $('#product_type').change(function(){
            let type = $(this).val();
            if(type === 'digital'){
                $('#digital_area').slideDown();
                $('#advance_area').slideUp();
                $('#variant_section').slideUp();
            } else {
                $('#digital_area').slideUp();
                $('#advance_area').slideDown();
                $('#variant_section').slideDown();
            }
        });

        // Initialize Select2 with multiple for size
        $('.variant-size-select').select2({
            multiple: true,
            width: '100%'
        });
        
        $('.variant-color-select').select2({
            width: '100%'
        });

        // Dynamic Variant Add/Remove
        let variantIndex = 1;
        $(".add-variant").click(function () {
            let wrapper = $("#variant-wrapper");
            let firstRow = wrapper.find('.variant-item').first().clone();
            
            // Clear inputs and fix select2
            firstRow.find('.select2-container').remove();
            firstRow.find('input').val('');
            firstRow.find('select').each(function(){
                let oldName = $(this).attr('name');
                if (oldName) {
                    // Handle size array name
                    if (oldName.includes('[size_id][]')) {
                        $(this).attr('name', 'variant_price[' + variantIndex + '][size_id][]');
                    } else {
                        $(this).attr('name', oldName.replace(/\[\d+\]/, '[' + variantIndex + ']'));
                    }
                }
                $(this).val(null).trigger('change');
            });
            firstRow.find('input').each(function(){
                let oldName = $(this).attr('name');
                if (oldName) {
                    $(this).attr('name', oldName.replace(/\[\d+\]/, '[' + variantIndex + ']'));
                }
            });

            firstRow.find('.btn-remove-row').removeClass('d-none');
            wrapper.append(firstRow);
            
            // Reinitialize Select2 for new row
            setTimeout(() => {
                firstRow.find('.variant-size-select').select2({
                    multiple: true,
                    width: '100%',
                    dropdownParent: $('#variant-wrapper')
                });
                firstRow.find('.variant-color-select').select2({
                    width: '100%',
                    dropdownParent: $('#variant-wrapper')
                });
            }, 100);
            
            variantIndex++;
        });

        $("body").on("click", ".btn-remove-row", function () {
            $(this).parents(".variant-item").remove();
        });
        
        // Handle form submission - expand multiple sizes into separate entries
        $('form[data-parsley-validate]').on('submit', function(e) {
            let variantData = [];
            let variantIndex = 0;
            
            // Collect all variant rows
            $('#variant-wrapper .variant-item').each(function() {
                let $row = $(this);
                let colorId = $row.find('.variant-color-select').val() || null;
                let selectedSizes = $row.find('.variant-size-select').val() || [];
                let price = $row.find('input[name*="[price]"]').val() || 0;
                let stock = $row.find('input[name*="[stock]"]').val() || 0;
                
                // Validate: At least color or size must be selected
                if (!colorId && selectedSizes.length === 0) {
                    // Skip if neither color nor size is selected
                    return;
                }
                
                // If sizes are selected, create separate entry for each size
                if (selectedSizes.length > 0) {
                    selectedSizes.forEach(function(sizeId) {
                        variantData.push({
                            index: variantIndex,
                            color_id: colorId,
                            size_id: sizeId,
                            price: price,
                            stock: stock
                        });
                        variantIndex++;
                    });
                } else {
                    // Only color selected (no size), create single entry
                    variantData.push({
                        index: variantIndex,
                        color_id: colorId,
                        size_id: null,
                        price: price,
                        stock: stock
                    });
                    variantIndex++;
                }
            });
            
            // Remove old variant_price inputs
            $(this).find('input[name*="variant_price"], select[name*="variant_price"]').each(function() {
                if ($(this).attr('name').includes('variant_price')) {
                    $(this).remove();
                }
            });
            
            // Add new hidden inputs for each variant
            variantData.forEach(function(variant) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'variant_price[' + variant.index + '][color_id]',
                    value: variant.color_id
                }).appendTo($('form[data-parsley-validate]'));
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'variant_price[' + variant.index + '][size_id]',
                    value: variant.size_id
                }).appendTo($('form[data-parsley-validate]'));
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'variant_price[' + variant.index + '][price]',
                    value: variant.price
                }).appendTo($('form[data-parsley-validate]'));
                
                $('<input>').attr({
                    type: 'hidden',
                    name: 'variant_price[' + variant.index + '][stock]',
                    value: variant.stock
                }).appendTo($('form[data-parsley-validate]'));
            });
        });

        // Wholesale toggle
        $("#is_wholesale").on("change", function () {
            if ($(this).is(':checked')) {
                $("#wholesale_area").slideDown();
                $("#wholesale_area input").prop('required', true);
            } else {
                $("#wholesale_area").slideUp();
                $("#wholesale_area input").prop('required', false);
            }
        });

        // Wholesale pricing tiers
        let wholesaleIndex = 1;
        $("body").on("click", ".add-wholesale-tier", function () {
            let wrapper = $("#wholesale-wrapper");
            let firstRow = wrapper.find(".variant-card").first().clone();
            
            firstRow.find('input').each(function(){
                let oldName = $(this).attr('name');
                $(this).attr('name', oldName.replace(/\[\d+\]/, '[' + wholesaleIndex + ']'));
                $(this).val('');
            });

            // Change add button to remove button
            firstRow.find('.add-wholesale-tier').removeClass('btn-success add-wholesale-tier').addClass('btn-danger btn-remove-wholesale').html('<i class="fa fa-trash"></i>');
            wrapper.append(firstRow);
            wholesaleIndex++;
        });

        $("body").on("click", ".btn-remove-wholesale", function () {
            $(this).parents(".variant-card").remove();
        });

        // AJAX Categories
        $("#category_id").on("change", function () {
            var id = $(this).val();
            if (id) {
                $.get("{{url('ajax-product-subcategory')}}?category_id=" + id, function(res){
                    $("#subcategory_id").empty().append('<option value="">Choose Sub Category</option>');
                    $.each(res, function(key, value){
                        $("#subcategory_id").append('<option value="'+key+'">'+value+'</option>');
                    });
                });
            }
        });

        $("#subcategory_id").on("change", function () {
            var id = $(this).val();
            if (id) {
                $.get("{{url('ajax-product-childcategory')}}?subcategory_id=" + id, function(res){
                    $("#childcategory_id").empty().append('<option value="">Choose Child Category</option>');
                    $.each(res, function(key, value){
                        $("#childcategory_id").append('<option value="'+key+'">'+value+'</option>');
                    });
                });
            }
        });
    });
</script>
@endsection
