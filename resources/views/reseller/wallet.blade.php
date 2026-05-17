@extends('reseller.layouts.app')

@section('title', 'Wallet Balance')
@section('page-title', 'Wallet Balance')

@push('styles')
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); /* Indigo */
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%); /* Emerald */
        --info-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);    /* Blue */
        --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }

    /* --- Wallet Cards --- */
    .wallet-card {
        border-radius: 20px;
        border: none;
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--card-shadow);
        transition: transform 0.3s ease;
    }
    
    .wallet-card:hover { transform: translateY(-5px); }

    /* Decorative Circle Background */
    .wallet-card::after {
        content: '';
        position: absolute;
        top: -30px; right: -30px;
        width: 150px; height: 150px;
        background: rgba(255,255,255,0.15);
        border-radius: 50%;
        pointer-events: none;
    }

    .bg-gradient-purple { background: var(--primary-gradient); }
    .bg-gradient-green { background: var(--success-gradient); }
    .bg-gradient-blue { background: var(--info-gradient); }

    .icon-box-glass {
        width: 50px; height: 50px;
        background: rgba(255,255,255,0.25);
        backdrop-filter: blur(4px);
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 15px;
    }

    .amount-text { font-size: 2.2rem; font-weight: 800; line-height: 1.2; }
    .label-text { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }

    /* --- Transaction Table --- */
    .table-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .custom-table thead th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 16px;
        border-bottom: 1px solid #e2e8f0;
    }

    .custom-table tbody td {
        padding: 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }
    
    .custom-table tbody tr:last-child td { border-bottom: none; }
    .custom-table tbody tr:hover { background-color: #f8fafc; }

    /* Badges */
    .badge-soft {
        padding: 6px 12px;
        border-radius: 30px;
        font-weight: 600;
        font-size: 0.75rem;
    }
    .badge-completed { background: #ecfdf5; color: #059669; }
    .badge-pending { background: #fffbeb; color: #d97706; }
    .badge-cancelled { background: #fef2f2; color: #dc2626; }
    .badge-processing { background: #eff6ff; color: #2563eb; }

    /* Avatar */
    .avatar-circle {
        width: 35px; height: 35px;
        background: #e0e7ff; color: #4338ca;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
    }
</style>
@endpush

@section('content')

    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1">My Wallet</h4>
        <p class="text-muted small">Manage your earnings and transactions</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-4 col-md-6">
            <div class="wallet-card bg-gradient-purple p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="icon-box-glass">
                        <i class="fas fa-wallet fa-lg"></i>
                    </div>
                    </div>
                <div>
                    <div class="label-text">Current Balance</div>
                    <div class="amount-text">৳ {{ number_format($walletBalance ?? 0, 2) }}</div>
                    <small class="opacity-75"><i class="fas fa-check-circle me-1"></i> Available for withdrawal</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="wallet-card bg-gradient-green p-4">
                <div class="icon-box-glass">
                    <i class="fas fa-chart-line fa-lg"></i>
                </div>
                <div>
                    <div class="label-text">Total Earned</div>
                    <div class="amount-text">৳ {{ number_format($totalEarned ?? 0, 2) }}</div>
                    <small class="opacity-75"><i class="fas fa-coins me-1"></i> Lifetime earnings</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="wallet-card bg-gradient-blue p-4">
                <div class="icon-box-glass">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                </div>
                <div>
                    <div class="label-text">Total Orders</div>
                    <div class="amount-text">{{ number_format($totalOrders ?? 0) }}</div>
                    <small class="opacity-75"><i class="fas fa-box me-1"></i> Orders with profit</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center p-4 border-bottom bg-white">
                    <h6 class="fw-bold m-0 text-dark"><i class="fas fa-history text-primary me-2"></i> Transaction History</h6>
                </div>
                
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date & Time</th>
                                <th>Customer Info</th>
                                <th>Profit</th>
                                <th>Loss</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr data-reseller-profit="{{ (float) ($order->reseller_profit ?? 0) }}" data-wallet-amount="{{ (float) ($order->wallet_display_amount ?? 0) }}">
                                    <td>
                                        <span class="fw-bold text-primary">#{{ $order->invoice_id ?? $order->id }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark">{{ $order->created_at->format('M d, Y') }}</span>
                                            <small class="text-muted">{{ $order->created_at->format('h:i A') }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle">
                                                {{ substr($order->customer->name ?? 'G', 0, 1) }}
                                            </div>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark small">{{ Str::limit($order->customer->name ?? 'Guest', 15) }}</span>
                                                <small class="text-muted" style="font-size: 11px;">{{ $order->customer->phone ?? 'N/A' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $walletAmount = (float) ($order->wallet_display_amount ?? ($order->reseller_profit ?? 0));
                                            $statusNameForLoss = strtolower((string) ($order->status->name ?? ''));
                                            $isReturnedOrder = str_contains($statusNameForLoss, 'return');
                                            $lossAmount = $isReturnedOrder ? (float) ($order->shipping_charge ?? 0) : 0;
                                        @endphp
                                        @if($isReturnedOrder)
                                            <span class="fw-bold text-danger">-৳{{ number_format(abs($walletAmount), 2) }}</span>
                                        @elseif($walletAmount > 0)
                                            <span class="fw-bold text-success">+৳{{ number_format($walletAmount, 2) }}</span>
                                        @else
                                            <span class="fw-bold text-muted">৳0.00</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isReturnedOrder && $lossAmount > 0)
                                            <span class="fw-bold text-danger">-&#2547;{{ number_format($lossAmount, 2) }}</span>
                                        @else
                                            <span class="fw-bold text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $status = $order->order_status ?? 0;
                                            $badgeClass = 'badge-pending';
                                            $statusName = $order->status->name ?? 'Pending';

                                            if ($status == 6) { $badgeClass = 'badge-completed'; } // Delivered
                                            elseif ($status == 11) { $badgeClass = 'badge-cancelled'; } // Cancelled
                                            elseif ($status == 5 || $status == 2) { $badgeClass = 'badge-processing'; } // Processing
                                        @endphp
                                        <span class="badge badge-soft {{ $badgeClass }}">
                                            {{ $statusName }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light rounded-circle border text-muted hover-primary">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center justify-content-center opacity-50">
                                            <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                                            <h6 class="text-muted">No transactions found</h6>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($orders->hasPages())
                <div class="d-flex justify-content-center mt-5 mb-4">
                    <style>
                        /* ফ্লোটিং পিল কন্টেইনার */
                        .pagination-pill {
                            background: #ffffff;
                            padding: 5px 8px;
                            border-radius: 50px; /* সম্পূর্ণ রাউন্ড শেপ */
                            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); /* সফট শ্যাডো */
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            border: 1px solid #f1f5f9;
                        }

                        /* গোল বাটন স্টাইল */
                        .page-link-circle {
                            width: 40px;
                            height: 40px;
                            border-radius: 50%; /* একদম গোল */
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: #64748b;
                            font-weight: 600;
                            font-size: 14px;
                            text-decoration: none;
                            transition: all 0.3s ease;
                            border: 1px solid transparent;
                        }

                        /* হোভার ইফেক্ট */
                        .page-link-circle:hover {
                            background-color: #f1f5f9;
                            color: #1e293b;
                            transform: translateY(-2px);
                        }

                        /* একটিভ বা সিলেক্টেড পেজ */
                        .page-link-circle.active {
                            background: #4f46e5; /* আপনার ব্র্যান্ড কালার */
                            color: #ffffff;
                            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); /* গ্লো ইফেক্ট */
                        }

                        /* ডিজেবল বাটন */
                        .page-link-circle.disabled {
                            color: #cbd5e1;
                            cursor: default;
                            pointer-events: none;
                        }
                    </style>

                    <div class="pagination-pill">
                        
                        {{-- Previous Button --}}
                        @if ($orders->onFirstPage())
                            <span class="page-link-circle disabled">
                                <i class="fas fa-chevron-left" style="font-size: 12px;"></i>
                            </span>
                        @else
                            <a href="{{ $orders->previousPageUrl() }}" class="page-link-circle" title="Previous">
                                <i class="fas fa-chevron-left" style="font-size: 12px;"></i>
                            </a>
                        @endif

                        {{-- Page Numbers --}}
                        @foreach(range(1, $orders->lastPage()) as $i)
                            @if($i >= $orders->currentPage() - 2 && $i <= $orders->currentPage() + 2)
                                @if ($i == $orders->currentPage())
                                    <span class="page-link-circle active">{{ $i }}</span>
                                @else
                                    <a href="{{ $orders->url($i) }}" class="page-link-circle">{{ $i }}</a>
                                @endif
                            @endif
                        @endforeach

                        {{-- Next Button --}}
                        @if ($orders->hasMorePages())
                            <a href="{{ $orders->nextPageUrl() }}" class="page-link-circle" title="Next">
                                <i class="fas fa-chevron-right" style="font-size: 12px;"></i>
                            </a>
                        @else
                            <span class="page-link-circle disabled">
                                <i class="fas fa-chevron-right" style="font-size: 12px;"></i>
                            </span>
                        @endif

                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    const table = document.querySelector('.custom-table');
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr[data-reseller-profit]');
    rows.forEach((row) => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 7) return;

        const profitCell = cells[3];
        const lossCell = cells[4];
        const statusCell = cells[5];
        const statusText = (statusCell.textContent || '').trim().toLowerCase();
        const resellerProfit = parseFloat(row.dataset.resellerProfit || '0');
        if (statusText.includes('return')) {
            profitCell.innerHTML = '<span class="fw-bold text-success text-decoration-line-through">+&#2547;' + resellerProfit.toFixed(2) + '</span>';
        }
        return;
        const walletAmount = parseFloat(row.dataset.walletAmount || '0');
        

        if (statusText.includes('return')) {
            
            profitCell.innerHTML = '<span class="fw-bold text-muted text-decoration-line-through">+৳' + resellerProfit.toFixed(2) + '</span>';
            lossCell.innerHTML = '<span class="fw-bold text-danger">-৳' + Math.abs(walletAmount).toFixed(2) + '</span>';
            const profitSpan = profitCell.querySelector('span');
            if (profitSpan) {
                profitSpan.classList.remove('text-muted');
                profitSpan.classList.add('text-success');
            }
        } else {
            lossCell.innerHTML = '<span class="fw-bold text-muted">-</span>';
        }

        statusCell.parentNode.insertBefore(lossCell, statusCell);
    });
});
</script>
@endpush
