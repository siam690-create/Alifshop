@extends('reseller.layouts.app')

@section('title', 'Invoice Details')
@section('page-title', 'Invoice Details')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h4 class="fw-bold text-dark mb-1">{{ $invoice->invoice_no }}</h4>
        <p class="text-muted small mb-0">
            {{ optional($invoice->period_started_at)->format('d M Y h:i A') ?? '-' }}
            -
            {{ optional($invoice->period_ended_at)->format('d M Y h:i A') ?? '-' }}
        </p>
    </div>
    <div>
        <a href="{{ route('reseller.invoices.index') }}" class="btn btn-light border">Back</a>
        <a href="{{ route('reseller.invoices.csv', $invoice->id) }}" class="btn btn-primary">Download CSV</a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md"><div class="text-muted small">Collected</div><h5>৳{{ number_format($invoice->total_collected, 2) }}</h5></div>
            <div class="col-md"><div class="text-muted small">Admin Price</div><h5>৳{{ number_format($invoice->total_admin_price ?? 0, 2) }}</h5></div>
            <div class="col-md"><div class="text-muted small">Delivery Fee</div><h5>৳{{ number_format($invoice->total_delivery_fee, 2) }}</h5></div>
            <div class="col-md"><div class="text-muted small">Receivable</div><h5>৳{{ number_format($invoice->net_payable, 2) }}</h5></div>
            <div class="col-md"><div class="text-muted small">Status</div><h5 class="text-capitalize">{{ $invoice->status }}</h5></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Order</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Collected</th>
                    <th>Admin Price</th>
                    <th>Delivery Fee</th>
                    <th>Payout</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->merchant_order }}</td>
                        <td>{{ optional($item->order_date)->format('d M Y h:i A') }}</td>
                        <td>{{ $item->recipient_name }}</td>
                        <td>{{ $item->recipient_phone }}</td>
                        <td>৳{{ number_format($item->collected_amount, 2) }}</td>
                        <td>৳{{ number_format($item->admin_price_total ?? 0, 2) }}</td>
                        <td>৳{{ number_format($item->delivery_fee, 2) }}</td>
                        <td class="fw-bold">৳{{ number_format($item->payout, 2) }}</td>
                        <td>{{ ucfirst($item->invoice_type) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
