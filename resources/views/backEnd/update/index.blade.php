@extends('backEnd.layouts.master')
@section('title', 'System Updates')

@section('content')

{{-- Professional Update Page Styles --}}
<style>
    :root {
        --up-primary: #4f46e5;
        --up-success: #10b981;
        --up-danger: #ef4444;
        --up-text: #1e293b;
        --up-muted: #64748b;
        --up-border: #e2e8f0;
        --up-bg: #f8fafc;
    }

    .update-page {
        font-family: 'Inter', sans-serif;
        padding: 24px 0 48px;
        background: var(--up-bg);
    }

    .script-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        border-radius: 16px;
        padding: 32px 40px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 10px 40px -10px rgba(79, 70, 229, 0.4);
        margin-bottom: 24px;
    }

    .script-hero-left { display: flex; align-items: center; gap: 20px; }
    
    .script-hero-icon {
        width: 64px; height: 64px;
        background: rgba(255,255,255,0.2);
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 28px;
    }

    .script-name { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
    
    .script-version-num {
        background: rgba(255,255,255,0.25);
        padding: 4px 12px; border-radius: 8px;
        font-weight: 700;
    }

    .license-badge.valid {
        background: #10b981;
        padding: 8px 16px; border-radius: 10px;
        font-size: 13px; font-weight: 600;
        display: inline-flex; align-items: center; gap: 8px;
    }

    .update-main-card {
        background: #fff; border-radius: 16px;
        border: 1px solid var(--up-border);
        overflow: hidden; margin-bottom: 24px;
    }

    .update-card-header { padding: 20px 28px; border-bottom: 1px solid var(--up-border); }
    .update-card-body { padding: 28px; }

    .info-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 16px;
    }

    .info-item { text-align: center; padding: 16px; background: var(--up-bg); border-radius: 10px; }
    .info-item-label { font-size: 11px; color: var(--up-muted); text-transform: uppercase; }
    .info-item-value { font-weight: 600; color: var(--up-text); }

    .update-empty-state.up-to-date { color: var(--up-success); text-align: center; padding: 40px; }
    .update-empty-state .icon { font-size: 48px; margin-bottom: 10px; }
</style>

<div class="content-wrapper">
    <div class="container-fluid update-page">
        
        <div class="d-flex align-items-center mb-4">
            <h3 class="m-0 fw-bold text-dark">
                <i class="fas fa-sync-alt text-primary me-2"></i> System Updates
            </h3>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                
                {{-- Hero Banner --}}
                <div class="script-hero">
                    <div class="script-hero-left">
                        <div class="script-hero-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div>
                            <div class="script-name">Ecommerce Pro</div>
                            <div class="script-version">
                                বর্তমান ভার্সন: <span class="script-version-num">v{{ $currentVersion ?? '1.0' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="script-hero-right">
                        <span class="license-badge valid">
                            <i class="fas fa-shield-check"></i> License Active
                        </span>
                    </div>
                </div>

                {{-- Main Content --}}
                <div class="update-main-card">
                    <div class="update-card-header">
                        <h4 class="mb-0 fw-bold"><i class="fas fa-cloud-download-alt me-2 text-primary"></i> আপডেট স্ট্যাটাস</h4>
                    </div>
                    <div class="update-card-body">
                        
                        {{-- Always show Up to Date --}}
                        <div class="update-empty-state up-to-date">
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                            <h5 class="fw-bold">আপনার সিস্টেমটি সম্পূর্ণ আপ-টু-ডেট আছে।</h5>
                            <p class="mb-0 mt-2 text-muted">সর্বশেষ ভার্সন: <strong>v{{ $currentVersion ?? '1.0' }}</strong></p>
                        </div>

                        {{-- System Info --}}
                        <hr class="my-4">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-item-label">Host</div>
                                <div class="info-item-value">{{ request()->getHost() }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">PHP Version</div>
                                <div class="info-item-value">{{ PHP_VERSION }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Laravel Version</div>
                                <div class="info-item-value">{{ app()->version() }}</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection