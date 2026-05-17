@extends('backEnd.layouts.master')
@section('title', 'Popup Management')

@section('content')

{{-- Professional CSS --}}
<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    .pro-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        background: #fff;
        overflow: hidden;
    }
    .table-pro thead th {
        background-color: #f8f9fa;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
        color: #6c757d;
        border-bottom: 1px solid #e9ecef;
        padding: 15px;
    }
    .table-pro tbody td {
        padding: 15px;
        vertical-align: middle;
        color: #495057;
        font-size: 14px;
        border-bottom: 1px solid #f1f1f1;
    }
    .status-badge {
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .status-active { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .status-inactive { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    
    /* Upload Preview Area */
    .upload-area {
        border: 2px dashed #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }
    .upload-area:hover { border-color: #3b82f6; background: #eff6ff; }
    .upload-icon { font-size: 24px; color: #94a3b8; margin-bottom: 8px; }
    .preview-img { max-height: 100px; border-radius: 6px; display: none; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid py-4">
    
    {{-- 1. Header Section --}}
    <div class="page-header">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Marketing Popups</h4>
            <small class="text-muted">Manage website promotional modals</small>
        </div>
        <button type="button" class="btn btn-primary px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#createPopupModal">
            <i class="fas fa-plus me-2"></i> Create New Popup
        </button>
    </div>

    {{-- 2. Data List Table --}}
    <div class="row">
        <div class="col-12">
            <div class="pro-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-pro mb-0">
                            <thead>
                                <tr>
                                    <th width="5%">SL</th>
                                    <th width="15%">Preview</th>
                                    <th width="25%">Campaign Title</th>
                                    <th width="30%">Offer Details</th>
                                    <th width="10%">Status</th>
                                    <th width="15%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($popups as $key => $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="bg-light rounded p-1 border d-inline-block">
                                            <img src="{{ url('public/'.$value->image) }}" width="60" height="40" style="object-fit: cover; border-radius: 4px;" alt="Popup">
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ $value->title }}</span>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block text-truncate" style="max-width: 250px;">
                                            {{ $value->description ?? 'No description' }}
                                        </small>
                                        @if($value->link)
                                            <small class="text-primary"><i class="fas fa-link fa-xs me-1"></i> Has Link</small>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.popup.status', $value->id) }}" class="btn status-badge {{ $value->status == 1 ? 'status-active' : 'status-inactive' }} btn-sm w-100">
                                            {{ $value->status == 1 ? 'Active' : 'Inactive' }}
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.popup.edit', $value->id) }}" class="btn btn-outline-primary btn-sm px-2" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('admin.popup.destroy', $value->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this popup?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm px-2" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="far fa-folder-open fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-0">No popups found. Create one to get started!</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 3. Create Modal --}}
<div class="modal fade" id="createPopupModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">🚀 New Campaign Popup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('admin.popup.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    
                    {{-- Error Alerts --}}
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show mb-4">
                            <ul class="mb-0 ps-3 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="row g-4">
                        {{-- Left Column: Image --}}
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-uppercase text-muted">Popup Image <span class="text-danger">*</span></label>
                            <div class="upload-area" onclick="document.getElementById('imageInput').click()">
                                <input type="file" name="image" id="imageInput" class="d-none" accept="image/*" onchange="previewImage(this)" required>
                                
                                <div id="uploadPlaceholder">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <p class="mb-1 text-dark fw-bold small">Click to Upload</p>
                                    <small class="text-muted d-block" style="font-size: 11px;">Recommended: 600x400px</small>
                                </div>
                                <img id="imgPreview" class="preview-img" src="#" alt="Preview">
                            </div>
                        </div>

                        {{-- Right Column: Inputs --}}
                        <div class="col-md-7">
                            <div class="form-group mb-3">
                                <label class="form-label fw-bold small text-muted">Campaign Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control bg-light border-0" name="title" placeholder="Ex: Flash Sale 50% Off" value="{{ old('title') }}" required>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label fw-bold small text-muted">Description / Subtitle</label>
                                <textarea class="form-control bg-light border-0" name="description" rows="3" placeholder="Short description regarding the offer...">{{ old('description') }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-muted">Button Text</label>
                                    <input type="text" class="form-control bg-light border-0" name="btn_text" value="{{ old('btn_text', 'Shop Now') }}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-muted">Button Link</label>
                                    <input type="text" class="form-control bg-light border-0" name="link" placeholder="https://" value="{{ old('link') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                         <div class="col-md-12">
                             <label class="form-label fw-bold small text-muted">Footer Text (Optional)</label>
                             <input type="text" class="form-control bg-light border-0" name="offer_end_text" placeholder="Ex: Offer ends on Sunday" value="{{ old('offer_end_text') }}">
                         </div>
                    </div>

                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="status" value="1" id="statusCheck" checked>
                        <label class="form-check-label fw-bold text-dark" for="statusCheck">Activate Immediately</label>
                    </div>

                </div>

                <div class="modal-footer border-top-0 pt-0 pb-4 pe-4">
                    <button type="button" class="btn btn-light text-muted fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i> Save Popup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 4. JavaScript --}}
<script>
    // Image Preview Function
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('uploadPlaceholder').style.display = 'none';
                var img = document.getElementById('imgPreview');
                img.src = e.target.result;
                img.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Auto Open Modal if Validation Fails
    @if($errors->any())
        var myModal = new bootstrap.Modal(document.getElementById('createPopupModal'));
        myModal.show();
    @endif
</script>

@endsection
