@extends('backEnd.layouts.master')
@section('title','Landing Page Create')

@section('css')
    <link href="{{asset('public/backEnd')}}/assets/libs/summernote/summernote-lite.min.css" rel="stylesheet" type="text/css" />
    <link href="{{asset('public/backEnd')}}/assets/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
    <link href="{{asset('public/backEnd')}}/assets/libs/flatpickr/flatpickr.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <a href="{{ route('campaign.index') }}" class="btn btn-primary rounded-pill">Manage</a>
                </div>
                <h4 class="page-title">Landing Page Create</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body">

                    <form action="{{ route('campaign.store') }}"
                          method="POST"
                          class="row"
                          enctype="multipart/form-data"
                          data-parsley-validate>
                        @csrf

                        @if ($errors->any())
                            <div class="col-12">
                                <div class="alert alert-danger">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        {{-- BASIC INFO --}}
                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Landing Page Title *</label>
                                <input type="text" name="name" value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        {{-- HERO / TOP CONTENT --}}
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Hero Badge Text (যেমন: ✅ খুলনার অরিজিনাল চুইঝাল)</label>
                                <input type="text" name="hero_badge_text" value="{{ old('hero_badge_text') }}"
                                       class="form-control">
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Hero Rating Text (যেমন: ৪.৯/৫ - ৪৮৯+ কাস্টমার)</label>
                                <input type="text" name="hero_rating_text" value="{{ old('hero_rating_text') }}"
                                       class="form-control">
                            </div>
                        </div>

                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Hero Title (H1)</label>
                                <input type="text" name="hero_title" value="{{ old('hero_title') }}"
                                       class="form-control">
                            </div>
                        </div>

                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Hero Subtitle</label>
                                <textarea name="hero_subtitle" rows="3"
                                          class="form-control">{{ old('hero_subtitle') }}</textarea>
                            </div>
                        </div>

<div class="row">
    <div class="col-md-4">
        <label>হিরো লিস্ট ১</label>
        <input type="text" name="hero_list_1" class="form-control" placeholder="যেমন: হোমমেড – কোন প্রিজারভেটিভ নেই">
    </div>

    <div class="col-md-4">
        <label>হিরো লিস্ট ২</label>
        <input type="text" name="hero_list_2" class="form-control">
    </div>

    <div class="col-md-4">
        <label>হিরো লিস্ট ৩</label>
        <input type="text" name="hero_list_3" class="form-control">
    </div>

    <div class="col-md-4 mt-3">
        <label>হিরো লিস্ট ৪</label>
        <input type="text" name="hero_list_4" class="form-control">
    </div>

    <div class="col-md-4 mt-3">
        <label>হিরো লিস্ট ৫</label>
        <input type="text" name="hero_list_5" class="form-control">
    </div>

    <div class="col-md-4 mt-3">
        <label>হিরো লিস্ট ৬</label>
        <input type="text" name="hero_list_6" class="form-control">
    </div>
</div>


                        {{-- BUTTON TEXT --}}
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Primary Button Text</label>
                                <input type="text" name="primary_btn_text" value="{{ old('primary_btn_text') }}"
                                       class="form-control" placeholder="এখনই অর্ডার করুন">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Secondary Button Text</label>
                                <input type="text" name="secondary_btn_text" value="{{ old('secondary_btn_text') }}"
                                       class="form-control" placeholder="লাইভ রান্না ভিডিও">
                            </div>
                        </div>

                        {{-- YOUTUBE VIDEO --}}
                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Youtube Video URL / ID</label>
                                <input type="text" name="video" value="{{ old('video') }}"
                                       class="form-control @error('video') is-invalid @enderror">
                                @error('video')
                                <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        {{-- MAIN PRODUCT SELECT --}}
                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Products *</label>
                                @php
                                    $oldSelectedProducts = old('product_id', []);
                                    $oldAutoSelectedProducts = array_map('intval', old('auto_select_product_ids', []));
                                @endphp
                                <select name="product_id[]" class="select2 form-control @error('product_id') is-invalid @enderror"
                                        multiple="multiple" data-placeholder="Choose ..." required>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ in_array((string) $product->id, array_map('strval', $oldSelectedProducts), true) ? 'selected' : '' }}>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                                @error('product_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted d-block mt-1">
                                    প্রথম সিলেক্ট করা প্রোডাক্টটাই মূল প্রোডাক্ট হিসেবে ধরে নেওয়া হবে।
                                </small>
                                <div class="mt-3">
                                    <label class="form-label d-block">Product Serial Order</label>
                                    <div id="campaign-product-order-list"
                                         class="border rounded-3 p-3 bg-white mb-3"
                                         data-selected='@json($oldSelectedProducts)'
                                         data-ordered='@json(array_map("intval", old("ordered_product_ids", $oldSelectedProducts)))'></div>
                                    <small class="text-muted d-block mt-1">
                                        Up / Down করে ঠিক করুন কোন product উপরে আর কোন product নিচে show হবে।
                                    </small>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label d-block">Auto Select Product</label>
                                    <div id="campaign-auto-select-list"
                                         class="border rounded-3 p-3 bg-light"
                                         data-selected='@json($oldSelectedProducts)'
                                         data-auto-selected='@json($oldAutoSelectedProducts)'></div>
                                    <small class="text-muted d-block mt-1">
                                        যেই product-এর switch On থাকবে, landing page-এ সেটি auto select হয়ে থাকবে।
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- FEATURE TEXTS --}}
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Feature 1 Title</label>
                                <input type="text" name="feature1_title" value="{{ old('feature1_title') }}"
                                       class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Feature 2 Title</label>
                                <input type="text" name="feature2_title" value="{{ old('feature2_title') }}"
                                       class="form-control">
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Feature 1 Text</label>
                                <textarea name="feature1_text" rows="3"
                                          class="form-control">{{ old('feature1_text') }}</textarea>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Feature 2 Text</label>
                                <textarea name="feature2_text" rows="3"
                                          class="form-control">{{ old('feature2_text') }}</textarea>
                            </div>
                        </div>

                        {{-- FEATURE IMAGES --}}
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Feature 1 Image *</label>
                            <input type="file" name="feature1_image"
                                   class="form-control @error('feature1_image') is-invalid @enderror">
                            @error('feature1_image')
                            <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Feature 2 Image</label>
                            <input type="file" name="feature2_image"
                                   class="form-control @error('feature2_image') is-invalid @enderror">
                            @error('feature2_image')
                            <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

{{-- WHY SECTION (4টি কার্ড) --}}
<div class="card mt-3">
    <div class="card-header">
        <h5>Why Section (কেন আমাদের প্রোডাক্ট সেরা?)</h5>
        <small class="text-muted">
            এখানে ৪টা কারণ/ফিচার সেট করতে পারো – আইকন, টাইটেল আর ছোট বিবরণ।
        </small>
    </div>
    <div class="card-body">
        <div class="row">

            {{-- WHY 1 --}}
            <div class="col-md-4 mb-3">
                <label>Why 1 Icon (emoji / icon class)</label>
                <input type="text" name="why1_icon" class="form-control"
                       value="{{ old('why1_icon') }}" placeholder="🏠 অথবা fa fa-home">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 1 Title</label>
                <input type="text" name="why1_title" class="form-control"
                       value="{{ old('why1_title') }}" placeholder="যেমন: হোমমেড">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 1 Text</label>
                <textarea name="why1_text" class="form-control" rows="2"
                          placeholder="সংক্ষিপ্ত বর্ণনা লিখুন...">{{ old('why1_text') }}</textarea>
            </div>

            {{-- WHY 2 --}}
            <div class="col-md-4 mb-3">
                <label>Why 2 Icon</label>
                <input type="text" name="why2_icon" class="form-control"
                       value="{{ old('why2_icon') }}" placeholder="🌿">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 2 Title</label>
                <input type="text" name="why2_title" class="form-control"
                       value="{{ old('why2_title') }}" placeholder="যেমন: অরিজিনাল মান">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 2 Text</label>
                <textarea name="why2_text" class="form-control" rows="2"
                          placeholder="সংক্ষিপ্ত বর্ণনা লিখুন...">{{ old('why2_text') }}</textarea>
            </div>

            {{-- WHY 3 --}}
            <div class="col-md-4 mb-3">
                <label>Why 3 Icon</label>
                <input type="text" name="why3_icon" class="form-control"
                       value="{{ old('why3_icon') }}" placeholder="🚚">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 3 Title</label>
                <input type="text" name="why3_title" class="form-control"
                       value="{{ old('why3_title') }}" placeholder="যেমন: দেশব্যাপী ডেলিভারি">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 3 Text</label>
                <textarea name="why3_text" class="form-control" rows="2"
                          placeholder="সংক্ষিপ্ত বর্ণনা লিখুন...">{{ old('why3_text') }}</textarea>
            </div>

            {{-- WHY 4 --}}
            <div class="col-md-4 mb-3">
                <label>Why 4 Icon</label>
                <input type="text" name="why4_icon" class="form-control"
                       value="{{ old('why4_icon') }}" placeholder="💬">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 4 Title</label>
                <input type="text" name="why4_title" class="form-control"
                       value="{{ old('why4_title') }}" placeholder="যেমন: সাপোর্ট">
            </div>
            <div class="col-md-4 mb-3">
                <label>Why 4 Text</label>
                <textarea name="why4_text" class="form-control" rows="2"
                          placeholder="সংক্ষিপ্ত বর্ণনা লিখুন...">{{ old('why4_text') }}</textarea>
            </div>

        </div>
    </div>
</div>



                        {{-- BANNER QUOTE SECTION --}}
                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Middle Banner Quote</label>
                                <input type="text" name="banner_quote" value="{{ old('banner_quote') }}"
                                       class="form-control" placeholder="“এমন ঝাঁজে নেই তো তুলনা!”">
                            </div>
                        </div>

                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Middle Banner Sub Text</label>
                                <textarea name="banner_subtext" rows="2"
                                          class="form-control">{{ old('banner_subtext') }}</textarea>
                            </div>
                        </div>

                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Middle Banner Image 1</label>
                            <input type="file" name="banner_image1"
                                   class="form-control @error('banner_image1') is-invalid @enderror">
                            @error('banner_image1')
                            <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Middle Banner Image 2</label>
                            <input type="file" name="banner_image2"
                                   class="form-control @error('banner_image2') is-invalid @enderror">
                            @error('banner_image2')
                            <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- CUSTOMER REVIEWS --}}
                        <div class="col-12 mt-2">
                            <h5 class="mb-2">Customer Reviews Section</h5>
                        </div>

                        <div class="col-sm-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Review Section Title</label>
                                <input type="text" name="review_section_title"
                                       value="{{ old('review_section_title','কাস্টমার রিভিউ') }}"
                                       class="form-control">
                            </div>
                        </div>

                        {{-- Review 1 --}}
                        <div class="col-12"><h6>Review 1</h6></div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="review1_name" value="{{ old('review1_name') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="review1_city" value="{{ old('review1_city') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">Stars (যেমন: ★★★★★)</label>
                            <input type="text" name="review1_stars" value="{{ old('review1_stars','★★★★★') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-12 mb-3">
                            <label class="form-label">Review Text</label>
                            <textarea name="review1_text" rows="3"
                                      class="form-control">{{ old('review1_text') }}</textarea>
                        </div>

                        {{-- Review 2 --}}
                        <div class="col-12"><h6>Review 2</h6></div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="review2_name" value="{{ old('review2_name') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="review2_city" value="{{ old('review2_city') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">Stars</label>
                            <input type="text" name="review2_stars" value="{{ old('review2_stars','★★★★★') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-12 mb-3">
                            <label class="form-label">Review Text</label>
                            <textarea name="review2_text" rows="3"
                                      class="form-control">{{ old('review2_text') }}</textarea>
                        </div>

                        {{-- Review 3 --}}
                        <div class="col-12"><h6>Review 3</h6></div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="review3_name" value="{{ old('review3_name') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="review3_city" value="{{ old('review3_city') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-4 mb-3">
                            <label class="form-label">Stars</label>
                            <input type="text" name="review3_stars" value="{{ old('review3_stars','★★★★☆') }}"
                                   class="form-control">
                        </div>
                        <div class="col-sm-12 mb-3">
                            <label class="form-label">Review Text</label>
                            <textarea name="review3_text" rows="3"
                                      class="form-control">{{ old('review3_text') }}</textarea>
                        </div>

                        {{-- GALLERY IMAGES --}}
                        <div class="col-12 mt-2">
                            <h5 class="mb-2">Gallery Images</h5>
                        </div>

                        @for($i=1;$i<=8;$i++)
                            <div class="col-sm-3 mb-3">
                                <label class="form-label">Gallery Image {{ $i }}</label>
                                <input type="file" name="gallery_image{{ $i }}"
                                       class="form-control">
                            </div>
                        @endfor

                        {{-- DESCRIPTION / EXTRA --}}
                        <div class="col-sm-12 mb-3">
                            <label class="form-label">Short Description</label>
                            <textarea name="short_description" class="summernote form-control">
                                {{ old('short_description') }}
                            </textarea>
                        </div>

                        <div class="col-sm-12 mb-3">
                            <label class="form-label">Long Description</label>
                            <textarea name="description" class="summernote form-control">
                                {{ old('description') }}
                            </textarea>
                        </div>
<div class="card mt-3">
    <div class="card-header">
        <h5>FAQ (সাধারণ জিজ্ঞাসা)</h5>
    </div>

    <div class="card-body">

        <div class="form-group mb-2">
            <label>FAQ প্রশ্ন ১:</label>
            <input type="text" name="faq_q1" class="form-control" placeholder="যেমন: চুইঝাল কতদিন ভালো থাকে?">
        </div>

        <div class="form-group mb-3">
            <label>FAQ উত্তর ১:</label>
            <textarea name="faq_a1" class="form-control" rows="2" placeholder="উত্তর লিখুন..."></textarea>
        </div>

        <div class="form-group mb-2">
            <label>FAQ প্রশ্ন ২:</label>
            <input type="text" name="faq_q2" class="form-control" placeholder="যেমন: ডেলিভারি চার্জ কত?">
        </div>

        <div class="form-group mb-3">
            <label>FAQ উত্তর ২:</label>
            <textarea name="faq_a2" class="form-control" rows="2" placeholder="উত্তর লিখুন..."></textarea>
        </div>

        <div class="form-group mb-2">
            <label>FAQ প্রশ্ন ৩:</label>
            <input type="text" name="faq_q3" class="form-control" placeholder="যেমন: কিভাবে অর্ডার কনফার্ম হবে?">
        </div>

        <div class="form-group mb-3">
            <label>FAQ উত্তর ৩:</label>
            <textarea name="faq_a3" class="form-control" rows="2" placeholder="উত্তর লিখুন..."></textarea>
        </div>

        <div class="form-group mb-2">
            <label>FAQ প্রশ্ন ৪:</label>
            <input type="text" name="faq_q4" class="form-control" placeholder="যেমন: পেমেন্ট আগে করতে হবে কি?">
        </div>

        <div class="form-group mb-3">
            <label>FAQ উত্তর ৪:</label>
            <textarea name="faq_a4" class="form-control" rows="2" placeholder="উত্তর লিখুন..."></textarea>
        </div>

    </div>
</div>

                        <div class="col-sm-6 mb-3">
                            <label class="form-label">Homepage Product Tittle</label>
                            <input type="text" name="billing_details" value="{{ old('billing_details') }}"
                                   class="form-control">
                        </div>

                        <div class="col-sm-6 mb-3">
                            <label class="d-block">Show Product Status</label>
                            <label class="switch">
                                <input type="checkbox" name="show_product" value="1" checked>
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="col-sm-6 mb-3">
                            <label class="d-block">Landing Page Status</label>
                            <label class="switch">
                                <input type="checkbox" name="status" value="1" {{ old('status', 1) ? 'checked' : '' }}>
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success">Create Campaign</button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('script')
    <script src="{{asset('public/backEnd/')}}/assets/libs/parsleyjs/parsley.min.js"></script>
    <script src="{{asset('public/backEnd/')}}/assets/js/pages/form-validation.init.js"></script>
    <script src="{{asset('public/backEnd/')}}/assets/libs/select2/js/select2.min.js"></script>
    <script src="{{asset('public/backEnd/')}}/assets/js/pages/form-advanced.init.js"></script>
    <script src="{{asset('public/backEnd/')}}/assets/libs/flatpickr/flatpickr.min.js"></script>
    <script src="{{asset('public/backEnd/')}}/assets/js/pages/form-pickers.init.js"></script>
    <script src="{{asset('public/backEnd/')}}/assets/libs/summernote/summernote-lite.min.js"></script>
    <script>
        $(".summernote").summernote({
            placeholder: "Enter Your Text Here"
        });
        $('.select2').select2();
        (function () {
            const productSelect = document.querySelector('select[name="product_id[]"]');
            const orderList = document.getElementById('campaign-product-order-list');
            const autoSelectList = document.getElementById('campaign-auto-select-list');

            if (!productSelect || !autoSelectList || !orderList) {
                return;
            }

            function getSelectedOptionsInOrder() {
                const selectedOptionMap = new Map(
                    Array.from(productSelect.selectedOptions).map(option => [String(option.value), option])
                );
                const savedOrderIds = JSON.parse(orderList.dataset.ordered || '[]').map(String);
                const orderedOptions = [];

                savedOrderIds.forEach(id => {
                    if (selectedOptionMap.has(id)) {
                        orderedOptions.push(selectedOptionMap.get(id));
                        selectedOptionMap.delete(id);
                    }
                });

                selectedOptionMap.forEach(option => orderedOptions.push(option));

                orderList.dataset.ordered = JSON.stringify(orderedOptions.map(option => Number(option.value)));
                return orderedOptions;
            }

            function renderOrderList() {
                const orderedOptions = getSelectedOptionsInOrder();

                if (!orderedOptions.length) {
                    orderList.innerHTML = '<span class="text-muted">প্রথমে product select করুন।</span>';
                    return;
                }

                orderList.innerHTML = orderedOptions.map((option, index) => `
                    <div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold">${option.text}</div>
                            <small class="text-muted">Serial: ${index + 1}</small>
                            <input type="hidden" name="ordered_product_ids[]" value="${option.value}">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary order-up" data-id="${option.value}" ${index === 0 ? 'disabled' : ''}>Up</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary order-down" data-id="${option.value}" ${index === orderedOptions.length - 1 ? 'disabled' : ''}>Down</button>
                        </div>
                    </div>
                `).join('');
            }

            function renderAutoSelectOptions() {
                const orderedOptions = getSelectedOptionsInOrder();
                const savedAutoSelectedIds = JSON.parse(autoSelectList.dataset.autoSelected || '[]').map(String);

                if (!orderedOptions.length) {
                    autoSelectList.innerHTML = '<span class="text-muted">প্রথমে product select করুন।</span>';
                    return;
                }

                autoSelectList.innerHTML = orderedOptions.map(option => {
                    const checked = savedAutoSelectedIds.includes(String(option.value)) ? 'checked' : '';
                    return `
                        <div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom">
                            <div>
                                <div class="fw-semibold">${option.text}</div>
                                <small class="text-muted">Landing page load হলে auto checked থাকবে</small>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="auto_select_product_ids[]" value="${option.value}" ${checked}>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            function moveOrderItem(productId, direction) {
                const currentOrder = JSON.parse(orderList.dataset.ordered || '[]').map(Number);
                const currentIndex = currentOrder.indexOf(Number(productId));
                const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

                if (currentIndex === -1 || targetIndex < 0 || targetIndex >= currentOrder.length) {
                    return;
                }

                [currentOrder[currentIndex], currentOrder[targetIndex]] = [currentOrder[targetIndex], currentOrder[currentIndex]];
                orderList.dataset.ordered = JSON.stringify(currentOrder);
                renderOrderList();
                renderAutoSelectOptions();
            }

            $(productSelect).on('change', function () {
                autoSelectList.dataset.autoSelected = JSON.stringify(
                    Array.from(autoSelectList.querySelectorAll('input[name="auto_select_product_ids[]"]:checked')).map(input => input.value)
                );
                const stillSelectedIds = Array.from(productSelect.selectedOptions).map(option => Number(option.value));
                const currentOrder = JSON.parse(orderList.dataset.ordered || '[]').map(Number);
                orderList.dataset.ordered = JSON.stringify(currentOrder.filter(id => stillSelectedIds.includes(id)));
                renderOrderList();
                renderAutoSelectOptions();
            });

            autoSelectList.addEventListener('change', function () {
                autoSelectList.dataset.autoSelected = JSON.stringify(
                    Array.from(autoSelectList.querySelectorAll('input[name="auto_select_product_ids[]"]:checked')).map(input => input.value)
                );
            });

            orderList.addEventListener('click', function (event) {
                const upButton = event.target.closest('.order-up');
                const downButton = event.target.closest('.order-down');

                if (upButton) {
                    moveOrderItem(upButton.dataset.id, 'up');
                }

                if (downButton) {
                    moveOrderItem(downButton.dataset.id, 'down');
                }
            });

            renderOrderList();
            renderAutoSelectOptions();
        })();
    </script>
@endsection
