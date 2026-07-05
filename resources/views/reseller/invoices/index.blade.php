@extends('reseller.layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@push('styles')
<style>
    .invoice-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .18);
        overflow: hidden;
    }
    .invoice-summary {
        background: linear-gradient(135deg, #6366f1, #4338ca);
        border-radius: 18px;
        color: #fff;
        padding: 24px;
    }
    .table-invoice th {
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        padding: 14px;
    }
    .table-invoice td {
        padding: 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .badge-soft {
        border-radius: 999px;
        font-weight: 700;
        padding: 6px 12px;
        font-size: 12px;
    }
    .badge-paid { background: #dcfce7; color: #166534; }
    .badge-pending { background: #fef3c7; color: #92400e; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Invoice</h4>
    <p class="text-muted small mb-0">Completed reseller orders will be grouped into daily, weekly, or monthly invoices.</p>
</div>

<div class="invoice-summary mb-4 d-flex justify-content-between align-items-center">
    <div>
        <div class="text-white-50 small fw-bold text-uppercase">Current Wallet</div>
        <h2 class="fw-bold mb-0">৳{{ number_format($user->wallet_balance ?? 0, 2) }}</h2>
    </div>
    <div class="text-end">
        <div class="text-white-50 small fw-bold text-uppercase">Payout Cycle</div>
        <h5 class="fw-bold mb-0 text-capitalize">{{ $user->reseller_payout_cycle ?? 'daily' }}</h5>
    </div>
</div>

<div class="invoice-card">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-1">Invoice History</h6>
            <p class="text-muted small mb-0">Download CSV to see per-order payout breakdown.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-invoice mb-0">
            <thead>
                <tr>
                    <th>Invoice ID</th>
                    <th>Period</th>
                    <th>Orders</th>
                    <th>Collected</th>
                    <th>Admin Price</th>
                    <th>Delivery Fee</th>
                    <th>Receivable</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="fw-bold">{{ $invoice->invoice_no }}</td>
                        <td class="small text-muted">
                            {{ optional($invoice->period_started_at)->format('d M Y h:i A') ?? '-' }}
                            -
                            {{ optional($invoice->period_ended_at)->format('d M Y h:i A') ?? '-' }}
                        </td>
                        <td>{{ $invoice->items_count }}</td>
                        <td>৳{{ number_format($invoice->total_collected, 2) }}</td>
                        <td>৳{{ number_format($invoice->total_admin_price ?? 0, 2) }}</td>
                        <td>৳{{ number_format($invoice->total_delivery_fee, 2) }}</td>
                        <td class="fw-bold">৳{{ number_format($invoice->net_payable, 2) }}</td>
                        <td>
                            <span class="badge-soft {{ $invoice->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('reseller.invoices.show', $invoice->id) }}" class="btn btn-sm btn-light border">View</a>
                            <a href="{{ route('reseller.invoices.csv', $invoice->id) }}" class="btn btn-sm btn-primary">CSV</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fas fa-file-invoice fa-3x mb-3 opacity-50"></i>
                            <div>No invoice generated yet.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
        <div class="p-3 border-top">
            {{ $invoices->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>
@endsection
