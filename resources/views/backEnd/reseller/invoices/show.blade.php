@extends('backEnd.layouts.master')
@section('title', 'Reseller Invoice Details')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">{{ $invoice->invoice_no }}</h4>
            <p class="text-muted small mb-0">
                {{ optional($invoice->user)->shop_name ?: optional($invoice->user)->name }} payout breakdown |
                {{ optional($invoice->period_started_at)->format('d M Y h:i A') ?? '-' }}
                -
                {{ optional($invoice->period_ended_at)->format('d M Y h:i A') ?? '-' }}
            </p>
        </div>
        <div>
            <a href="{{ route('admin.reseller.invoices.index') }}" class="btn btn-light border">Back</a>
            <a href="{{ route('admin.reseller.invoices.csv', $invoice->id) }}" class="btn btn-primary">Download CSV</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md"><small class="text-muted">Collected</small><h5>৳{{ number_format($invoice->total_collected, 2) }}</h5></div>
                <div class="col-md"><small class="text-muted">Admin Price</small><h5>৳{{ number_format($invoice->total_admin_price ?? 0, 2) }}</h5></div>
                <div class="col-md"><small class="text-muted">Delivery Fee</small><h5>৳{{ number_format($invoice->total_delivery_fee, 2) }}</h5></div>
                <div class="col-md"><small class="text-muted">Receivable</small><h5>৳{{ number_format($invoice->net_payable, 2) }}</h5></div>
                <div class="col-md"><small class="text-muted">Status</small><h5 class="text-capitalize">{{ $invoice->status }}</h5></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Consignment</th>
                        <th>Order Date</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Collected</th>
                        <th>Admin Price</th>
                        <th>Delivery Fee</th>
                        <th>Payout</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                        <tr>
                            <td>{{ $item->consignment_id }}</td>
                            <td>{{ optional($item->order_date)->format('d M Y h:i A') }}</td>
                            <td>{{ $item->recipient_name }}</td>
                            <td>{{ $item->recipient_phone }}</td>
                            <td>৳{{ number_format($item->collected_amount, 2) }}</td>
                            <td>৳{{ number_format($item->admin_price_total ?? 0, 2) }}</td>
                            <td>৳{{ number_format($item->delivery_fee, 2) }}</td>
                            <td class="fw-bold">৳{{ number_format($item->payout, 2) }}</td>
                            <td>{{ ucfirst($item->invoice_type) }}</td>
                            <td>{{ $item->status_snapshot }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
