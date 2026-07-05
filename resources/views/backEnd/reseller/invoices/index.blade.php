@extends('backEnd.layouts.master')
@section('title', 'Reseller Invoices')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i data-feather="file-text" class="text-primary me-2"></i> Reseller Invoices</h4>
            <p class="text-muted small mb-0">Generate daily, weekly, or monthly payout invoices from completed orders.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('admin.reseller.invoices.generate') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label fw-bold small">Generate For</label>
                    <select name="reseller_id" class="form-select">
                        <option value="">All due resellers</option>
                        @foreach($resellers as $reseller)
                            <option value="{{ $reseller->id }}">{{ $reseller->shop_name ?: $reseller->name }} ({{ ucfirst($reseller->reseller_payout_cycle ?? 'daily') }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" value="1" name="force" id="forceGenerate">
                        <label class="form-check-label" for="forceGenerate">Force generate selected reseller</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('admin.reseller.invoices.index') }}" class="btn btn-light border w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice ID</th>
                        <th>Reseller</th>
                        <th>Cycle</th>
                        <th>Period</th>
                        <th>Orders</th>
                        <th>Collected</th>
                        <th>Admin Price</th>
                        <th>Delivery Fee</th>
                        <th>Receivable</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="fw-bold">{{ $invoice->invoice_no }}</td>
                            <td>
                                <div class="fw-bold">{{ optional($invoice->user)->shop_name ?: optional($invoice->user)->name }}</div>
                                <small class="text-muted">{{ optional($invoice->user)->email }}</small>
                            </td>
                            <td class="text-capitalize">{{ $invoice->cycle }}</td>
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
                            <td><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($invoice->status) }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.reseller.invoices.show', $invoice->id) }}" class="btn btn-sm btn-light border">View</a>
                                <a href="{{ route('admin.reseller.invoices.csv', $invoice->id) }}" class="btn btn-sm btn-outline-primary">CSV</a>
                                @if($invoice->status === 'pending')
                                    <form action="{{ route('admin.reseller.invoices.paid', $invoice->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success" onclick="return confirm('Mark this invoice as paid?')">Mark Paid</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-5 text-muted">No reseller invoice found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="p-3 border-top">{{ $invoices->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>
</div>
@endsection
