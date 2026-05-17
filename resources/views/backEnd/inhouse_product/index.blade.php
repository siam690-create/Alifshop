@extends('backEnd.layouts.master')
@section('title','Inhouse Products')
@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    /* আপনার দেওয়া কাস্টম আধুনিক স্টাইল */
    .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0.75rem; }
    .table thead { background-color: #f8f9fa; }
    .table thead th { border-top: none; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6c757d; font-weight: 700; padding: 12px 15px; }
    .table tbody td { vertical-align: middle; padding: 12px 15px; border-color: #f1f3f5; }
    
    /* ইমেজ স্টাইল */
    .product-img { border-radius: 8px; object-fit: cover; border: 1px solid #ebedf2; transition: transform 0.2s ease; }
    .product-img:hover { transform: scale(1.1); }

    /* বাটন ও ব্যাজ স্টাইল */
    .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; transition: 0.3s; border: none; }
    .btn-edit { background: #e3f2fd; color: #2196f3; }
    .btn-edit:hover { background: #2196f3; color: #fff; }
    .btn-delete { background: #ffebee; color: #f44336; }
    .btn-delete:hover { background: #f44336; color: #fff; }
    .btn-status-toggle { background: #f1f3f5; color: #495057; }
    .btn-status-toggle:hover { background: #dee2e6; }
    .btn-status-active { background: #e8f5e9; color: #2e7d32; }
    .btn-status-active:hover { background: #2e7d32; color: #fff; }

    /* সফট ব্যাজ কালার */
    .badge-soft-primary { background-color: #e1f5fe; color: #039be5; }
    .badge-soft-success { background-color: #e8f5e9; color: #2e7d32; }
    .badge-soft-warning { background-color: #fff3e0; color: #ef6c00; }
    .badge-soft-danger { background-color: #ffebee; color: #c62828; }
    .badge-soft-info { background-color: #e0f7fa; color: #00838f; }
    .badge-soft-secondary { background-color: #f1f3f5; color: #495057; }

    .action2-btn { list-style: none; padding: 0; margin: 0; display: flex; gap: 8px; flex-wrap: wrap; }
    .toolbar-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .toolbar-divider {
        width: 1px;
        height: 28px;
        background: #dbe3ef;
        margin: 0 4px;
    }
    .status-filter-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 88px;
        height: 34px;
        padding: 0 14px;
        border: 1px solid #dbe3ef;
        border-radius: 999px;
        background: #fff;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .status-filter-chip:hover {
        border-color: #6658dd;
        color: #6658dd;
        background: #f5f3ff;
    }
    .status-filter-chip.active {
        border-color: #6658dd;
        background: #6658dd;
        color: #fff;
        box-shadow: 0 10px 20px rgba(102, 88, 221, 0.16);
    }
    .page-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    /* নাম এবং টেক্সট স্টাইল */
    .product-title { font-size: 14px; font-weight: 600; color: #343a40; margin: 0; }
    .text-small { font-size: 11px; }
    .filter-form .form-select,
    .filter-form .form-control { min-width: 0; }
    .feature-toggle-wrap { min-width: 110px; }
    .feature-toggle-wrap .form-check { margin-bottom: 0; }
    .feature-toggle-wrap .form-check-input { cursor: pointer; }
    .feature-badge-inline {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        background: #fff3e0;
        color: #ef6c00;
    }
</style>

<div class="container-fluid">
    @php
        $statusFilter = (string) request('status', '');
        $allFilterQuery = array_merge(request()->query(), ['status' => '']);
        $activeFilterQuery = array_merge(request()->query(), ['status' => '1']);
        $inactiveFilterQuery = array_merge(request()->query(), ['status' => '0']);
    @endphp
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between py-3">
                <h4 class="page-title mb-0">Inhouse Products Library</h4>
                <div class="page-title-right page-header-actions">
                    <a href="{{ route('inhouse.products.export_basic_csv') }}" class="btn btn-info rounded-pill shadow-sm">
                        <i class="fe-file-text me-1"></i> Export CSV
                    </a>
                    <a href="{{ route('inhouse.products.export') }}" class="btn btn-success rounded-pill shadow-sm">
                        <i class="fe-download me-1"></i> Export Excel
                    </a>
                    <button type="button" class="btn btn-outline-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#productImportModal">
                        <i class="fe-upload me-1"></i> Import Excel + ZIP
                    </button>
                    <a href="{{route('products.create')}}" class="btn btn-danger rounded-pill shadow-sm">
                        <i class="fe-plus me-1"></i> Add New Product
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    
                    <div class="row mb-3 align-items-start">
                        <div class="col-lg-5 col-md-12 mb-2 mb-lg-0">
                            <div class="toolbar-left">
                                <div>
                                    <button data-url="{{ route('products.update_deals') }}" data-status="1" class="btn btn-sm btn-outline-success rounded-pill hotdeal_update">
                                        <i class="fe-thumbs-up me-1"></i> Set Deal
                                    </button>
                                </div>
                                <div>
                                    <button data-url="{{ route('products.update_deals') }}" data-status="0" class="btn btn-sm btn-outline-danger rounded-pill hotdeal_update">
                                        <i class="fe-thumbs-down me-1"></i> Remove Deal
                                    </button>
                                </div>
                                <div class="toolbar-divider d-none d-lg-block"></div>
                                <div>
                                    <button data-url="{{ route('products.update_status') }}" data-status="1" class="btn btn-sm btn-primary rounded-pill update_status">
                                        <i class="fe-check me-1"></i> Active Selected
                                    </button>
                                </div>
                                <div>
                                    <button data-url="{{ route('products.update_status') }}" data-status="0" class="btn btn-sm btn-light border rounded-pill update_status">
                                        <i class="fe-x me-1"></i> Inactive Selected
                                    </button>
                                </div>
                                <div class="toolbar-divider d-none d-lg-block"></div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <a href="{{ route('inhouse.products.index', $allFilterQuery) }}" class="status-filter-chip {{ $statusFilter === '' ? 'active' : '' }}">All</a>
                                    <a href="{{ route('inhouse.products.index', $activeFilterQuery) }}" class="status-filter-chip {{ $statusFilter === '1' ? 'active' : '' }}">Active</a>
                                    <a href="{{ route('inhouse.products.index', $inactiveFilterQuery) }}" class="status-filter-chip {{ $statusFilter === '0' ? 'active' : '' }}">Inactive</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7 col-md-12">
                            <form method="GET" action="{{ route('inhouse.products.index') }}" class="filter-form" id="inhouseProductFilterForm">
                                <div class="row g-2 align-items-center">
                                    <div class="col-lg-2 col-md-6">
                                        <select name="status" id="status" class="form-select form-select-sm">
                                            <option value="">All Status</option>
                                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-6">
                                        <select name="category_id" id="category_id" class="form-select form-select-sm">
                                            <option value="">All Category</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-6">
                                        <select name="subcategory_id" id="subcategory_id" class="form-select form-select-sm">
                                            <option value="">All Subcategory</option>
                                            @foreach($subcategories as $subcategory)
                                                <option value="{{ $subcategory->id }}" {{ request('subcategory_id') == $subcategory->id ? 'selected' : '' }}>
                                                    {{ $subcategory->subcategoryName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-6">
                                        <select name="childcategory_id" id="childcategory_id" class="form-select form-select-sm">
                                            <option value="">All Childcategory</option>
                                            @foreach($childcategories as $childcategory)
                                                <option value="{{ $childcategory->id }}" {{ request('childcategory_id') == $childcategory->id ? 'selected' : '' }}>
                                                    {{ $childcategory->childcategoryName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-4 col-md-12">
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="keyword" class="form-control border-end-0" placeholder="Search by name or product code..." value="{{ request('keyword') }}">
                                            <button class="btn btn-info border-start-0 px-3" type="submit">
                                                <i class="fe-search"></i>
                                            </button>
                                            <a href="{{ route('inhouse.products.index') }}" class="btn btn-light border">
                                                <i class="fe-refresh-cw"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input checkall" id="parentCheck">
                                        </div>
                                    </th>
                                    <th>SL</th>
                                    <th>Image</th>
                                    <th style="width: 250px;">Product Info</th>
                                    <th>Category</th>
                                    <th>Price & Stock</th>
                                    <th>Features</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data as $key=>$value)
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input checkbox" value="{{$value->id}}">
                                        </div>
                                    </td>
                                    <td>{{ $data->firstItem() + $key }}</td>
                                    
                                    <td>
                                        <img src="{{ asset($value->image ? $value->image->image : 'storage/uploads/placeholder.png') }}" 
                                             class="product-img shadow-sm" alt="product" width="55" height="55">
                                    </td>

                                    <td>
                                        <h5 class="product-title">{{ Str::limit($value->name, 40) }}</h5>
                                        @php
                                            $isDigital = (isset($value->is_digital) && $value->is_digital) || (isset($value->product_type) && $value->product_type === 'digital');
                                        @endphp
                                        <span class="badge {{ $isDigital ? 'badge-soft-primary' : 'badge-soft-info' }} mt-1 font-size-10">
                                            <i class="{{ $isDigital ? 'fe-file-text' : 'fe-box' }} me-1"></i>{{ $isDigital ? 'Digital' : 'Physical' }}
                                        </span>
                                    </td>

                                    <td>
                                        <p class="m-0 fw-bold text-muted font-size-12">{{$value->category ? $value->category->name : 'Uncategorized'}}</p>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-dark">৳{{ number_format($value->new_price, 2) }}</div>
                                        <small class="d-block text-info text-small">
                                            Reseller:
                                            <span class="fw-bold">
                                                {{ $value->reseller_price !== null ? '৳' . number_format($value->reseller_price, 2) : 'N/A' }}
                                            </span>
                                        </small>
                                        <small class="text-muted text-small">Stock: <span class="{{ $value->stock <= 5 ? 'text-danger fw-bold' : 'text-success' }}">{{$value->stock}}</span></small>
                                    </td>

                                    <td>
                                        <div class="feature-toggle-wrap">
                                            <div class="form-check form-switch d-inline-flex align-items-center">
                                                <input
                                                    class="form-check-input hotdeal-toggle"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="hotdeal_{{ $value->id }}"
                                                    data-id="{{ $value->id }}"
                                                    {{ $value->topsale == 1 ? 'checked' : '' }}>
                                            </div>

                                            <div class="feature-badges">
                                                @if($value->topsale==1)
                                                    <span class="feature-badge-inline hotdeal-badge">
                                                        <i class="fe-zap"></i> Hot Deal
                                                    </span>
                                                @else
                                                    <span class="text-muted text-small hotdeal-empty">-</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        @if($value->status==1)
                                            <span class="badge badge-soft-success px-2 py-1">Active</span>
                                        @else
                                            <span class="badge badge-soft-danger px-2 py-1">Inactive</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            {{-- Status Toggle --}}
                                            @if($value->status == 1)
                                                <form method="post" action="{{route('products.inactive')}}" class="d-inline"> 
                                                    @csrf
                                                    <input type="hidden" value="{{$value->id}}" name="hidden_id">        
                                                    <button type="submit" class="btn-action btn-status-toggle" title="Deactivate"><i class="fe-thumbs-down"></i></button>
                                                </form>
                                            @else
                                                <form method="post" action="{{route('products.active')}}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" value="{{$value->id}}" name="hidden_id">        
                                                    <button type="submit" class="btn-action btn-status-active" title="Activate"><i class="fe-thumbs-up"></i></button>
                                                </form>
                                            @endif

                                            {{-- Edit --}}
                                            <a href="{{route('products.edit',$value->id)}}" class="btn-action btn-edit" title="Edit">
                                                <i class="fe-edit"></i>
                                            </a>

                                            {{-- Delete --}}
                                            <form method="post" action="{{route('products.destroy')}}" class="d-inline" onsubmit="return confirm('Are you sure?');">        
                                                @csrf
                                                <input type="hidden" value="{{$value->id}}" name="hidden_id">
                                                <button type="submit" class="btn-action btn-delete" title="Delete">
                                                    <i class="fe-trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fe-search font-size-24 d-block mb-2"></i>
                                            No products found!
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap">
                        <div class="text-muted font-size-13 mb-2 mb-md-0">
                            Showing {{ $data->firstItem() }} to {{ $data->lastItem() }} of {{ $data->total() }} results
                        </div>
                        <div class="custom-paginate">
                            {{$data->links('pagination::bootstrap-4')}}
                        </div>
                    </div>
                </div> 
            </div> 
        </div>
    </div>
</div>

<div class="modal fade" id="productImportModal" tabindex="-1" aria-labelledby="productImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="productImportModalLabel">Import Inhouse Products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('inhouse.products.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <strong>Workflow:</strong> first click <code>Export Excel</code>, edit that file in Excel, then upload the edited file here with a ZIP containing the image files.
                    </div>
                    <div class="mb-3">
                        <label for="product_sheet" class="form-label fw-bold">Excel-compatible sheet</label>
                        <input type="file" name="product_sheet" id="product_sheet" class="form-control" accept=".xls,.csv,.txt,.html,.htm" required>
                        <small class="text-muted">Required columns: <code>name</code>, <code>description</code>, <code>category_name</code>.</small>
                    </div>
                    <div class="mb-3">
                        <label for="product_images_zip" class="form-label fw-bold">Images ZIP</label>
                        <input type="file" name="product_images_zip" id="product_images_zip" class="form-control" accept=".zip">
                        <small class="text-muted">Match ZIP filenames with <code>primary_image_filename</code> or <code>gallery_filenames</code> from the sheet.</small>
                    </div>
                    <div class="bg-light rounded p-3 small text-muted">
                        <strong>Available columns:</strong>
                        product_code, name, slug, category_name, subcategory_name, childcategory_name, brand_name, product_type, description, short_description, new_price, old_price, purchase_price, reseller_price, stock, status, free_delivery, topsale, feature_product, meta_title, meta_description, meta_keywords, primary_image_filename, primary_image_path, gallery_filenames
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-pill">
                        <i class="fe-upload me-1"></i> Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    const subcategoryUrl = "{{ url('ajax-product-subcategory') }}";
    const childcategoryUrl = "{{ url('ajax-product-childcategory') }}";

    // Select all checkboxes
    $(".checkall").on('change', function(){
        $(".checkbox").prop('checked', $(this).is(":checked"));
    });

    $('#category_id').on('change', function () {
        const categoryId = $(this).val();
        const $subcategory = $('#subcategory_id');
        const $childcategory = $('#childcategory_id');

        $subcategory.html('<option value="">All Subcategory</option>');
        $childcategory.html('<option value="">All Childcategory</option>');

        if (!categoryId) {
            return;
        }

        $.get(subcategoryUrl, { category_id: categoryId }, function (data) {
            $.each(data, function (id, name) {
                $subcategory.append(`<option value="${id}">${name}</option>`);
            });
        });
    });

    $('#subcategory_id').on('change', function () {
        const subcategoryId = $(this).val();
        const $childcategory = $('#childcategory_id');

        $childcategory.html('<option value="">All Childcategory</option>');

        if (!subcategoryId) {
            return;
        }

        $.get(childcategoryUrl, { subcategory_id: subcategoryId }, function (data) {
            $.each(data, function (id, name) {
                $childcategory.append(`<option value="${id}">${name}</option>`);
            });
        });
    });

    function getCheckedIds() {
        return $('input.checkbox:checked').map(function(){ return $(this).val(); }).get();
    }

    function sendBulkRequest(url, status) {
        var ids = getCheckedIds();
        if(ids.length === 0){
            if (typeof toastr !== 'undefined') {
                toastr.error('Please select at least one product!');
            } else {
                alert('Please select at least one product!');
            }
            return;
        }

        var token = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: url,
            type: 'POST',
            data: JSON.stringify({ product_ids: ids, status: status }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': token },
            success: function(res){
                if(res.status === 'success'){
                    if (typeof toastr !== 'undefined') {
                        toastr.success(res.message);
                    }
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(res.message || 'Action failed');
                    }
                }
            },
            error: function(xhr){
                let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Server Error';
                if (typeof toastr !== 'undefined') { toastr.error(msg); } else { alert(msg); }
            }
        });
    }

    // Handle Bulk Clicks
    $(document).on('click', '.hotdeal_update, .update_status', function(e){
        e.preventDefault();
        var url = $(this).data('url');
        var status = $(this).data('status');
        if(url) sendBulkRequest(url, status);
    });

    $(document).on('change', '.hotdeal-toggle', function () {
        const $toggle = $(this);
        const productId = $toggle.data('id');
        const status = $toggle.is(':checked') ? 1 : 0;
        const $wrap = $toggle.closest('.feature-toggle-wrap');
        const $badgeBox = $wrap.find('.feature-badges');
        const token = $('meta[name="csrf-token"]').attr('content');

        $toggle.prop('disabled', true);

        $.ajax({
            url: "{{ route('products.update_deals') }}",
            type: 'POST',
            data: JSON.stringify({ product_ids: [productId], status: status }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': token },
            success: function (res) {
                if (res.status === 'success') {
                    if (status === 1) {
                        $badgeBox.html('<span class="feature-badge-inline hotdeal-badge"><i class="fe-zap"></i> Hot Deal</span>');
                    } else {
                        $badgeBox.html('<span class="text-muted text-small hotdeal-empty">-</span>');
                    }

                    if (typeof toastr !== 'undefined') {
                        toastr.success(status === 1 ? 'Product added to Hot Deal' : 'Hot Deal turned off');
                    }
                } else {
                    $toggle.prop('checked', !status);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(res.message || 'Hot Deal update failed');
                    }
                }
            },
            error: function (xhr) {
                $toggle.prop('checked', !status);
                let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Server Error';
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            },
            complete: function () {
                $toggle.prop('disabled', false);
            }
        });
    });
});
</script>
@endsection
