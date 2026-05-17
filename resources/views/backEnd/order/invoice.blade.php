@extends('backEnd.layouts.master')
@section('title','Order Invoice')
@section('content')
<style>
    .customer-invoice {
        margin: 25px 0;
    }
    .invoice_btn{
        margin-bottom: 15px;
    }
    p{
        margin:0;
    }
    td{
        font-size: 16px;
    }
   @page { 
    margin:0px;
    }
   @media print {
    .invoice-innter{
        margin-left: -120px !important;
    }
    .invoice_btn{
        margin-bottom: 0 !important;
    }
    td{
        font-size: 18px;
    }
    p{
        margin:0;
    }
    header,footer,.no-print,.left-side-menu,.navbar-custom {
      display: none !important;
    }
  }
    .history-card {
        width: 760px;
        margin: 24px auto 0;
        background: #fff;
        border-radius: 16px;
        padding: 28px 30px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }
    .history-card-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 24px;
    }
    .history-card-title h4 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
    }
    .history-card-title small {
        color: #64748b;
        font-size: 13px;
    }
    .history-timeline {
        position: relative;
        padding-left: 78px;
    }
    .history-timeline::before {
        content: "";
        position: absolute;
        left: 31px;
        top: 8px;
        bottom: 8px;
        width: 4px;
        border-radius: 999px;
        background: linear-gradient(180deg, #1f2937 0%, #4b6b80 100%);
    }
    .history-item {
        position: relative;
        margin-bottom: 22px;
    }
    .history-item:last-child {
        margin-bottom: 0;
    }
    .history-status-dot {
        position: absolute;
        left: -78px;
        top: 2px;
        width: 62px;
        height: 62px;
        border-radius: 999px;
        background: #3d6277;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 12px;
        line-height: 1.15;
        font-weight: 700;
        padding: 8px;
        z-index: 2;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
    }
    .history-status-dot.history-status-highlight {
        background: #1f2937;
    }
    .history-item-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        background: #f8fafc;
    }
    .history-item-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 10px;
    }
    .history-item-top h5 {
        margin: 0 0 5px;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }
    .history-item-top p {
        margin: 0;
        font-size: 13px;
        color: #475569;
        line-height: 1.6;
    }
    .history-meta {
        text-align: right;
        min-width: 165px;
    }
    .history-meta strong {
        display: block;
        font-size: 18px;
        color: #0f172a;
        line-height: 1.25;
        margin-bottom: 4px;
    }
    .history-meta span {
        display: block;
        font-size: 12px;
        color: #64748b;
        line-height: 1.45;
    }
    .history-event-type {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        border-radius: 999px;
        background: #e0f2fe;
        color: #0369a1;
        font-size: 11px;
        font-weight: 700;
        padding: 5px 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .history-changes {
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 8px;
    }
    .history-change-row {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 12px;
        font-size: 13px;
        align-items: start;
    }
    .history-change-row strong {
        color: #0f172a;
    }
    .history-change-values {
        color: #475569;
        line-height: 1.55;
    }
    .history-change-values del {
        color: #dc2626;
        margin-right: 8px;
    }
    .history-change-values ins {
        color: #15803d;
        text-decoration: none;
        font-weight: 700;
    }
    .history-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        background: #f8fafc;
        padding: 24px;
        text-align: center;
        color: #64748b;
        font-size: 14px;
    }
    @media (max-width: 820px) {
        .history-card {
            width: 100%;
            padding: 20px 18px;
        }
        .history-timeline {
            padding-left: 0;
        }
        .history-timeline::before {
            display: none;
        }
        .history-status-dot {
            position: static;
            margin-bottom: 12px;
        }
        .history-item-top {
            flex-direction: column;
        }
        .history-meta {
            text-align: left;
            min-width: 0;
        }
        .history-change-row {
            grid-template-columns: 1fr;
            gap: 4px;
        }
    }
</style>

<section class="customer-invoice ">
    <div class="container">
        <div class="row">
            <div class="col-sm-6">
                <a href="/admin/order/all" class="no-print"><strong><i class="fe-arrow-left"></i> Back To Order</strong></a>
            </div>
            <div class="col-sm-6 text-end">
                <button onclick="printFunction()" class="no-print btn btn-xs btn-success waves-effect waves-light"><i class="fa fa-print"></i></button>
            </div>

            <div class="col-sm-12 mt-3">
                <div class="invoice-innter" style="width:760px;margin: 0 auto;background: #fff;overflow: hidden;padding: 30px;padding-top: 0;">
                    <table style="width:100%">
                        <tr>
                            <td style="width: 40%; float: left; padding-top: 15px;">
                                <img src="{{asset($generalsetting->white_logo)}}" width="190px" style="margin-top:25px !important" alt="">
                                <p style="font-size: 14px; color: #222; margin: 20px 0;">
                                    <strong>Payment Method:</strong> 
                                    <span style="text-transform: uppercase;">{{$order->payment?$order->payment->payment_method:''}}</span>
                                </p>

                                <!-- ✅ Payment Gateway + Status অংশ -->
                                <div style="margin-bottom:15px;">
                                    <p><strong>Payment Gateway:</strong> {{ ucfirst($order->payment_gateway ?? 'N/A') }}</p>
                                    <p><strong>Payment Status:</strong></p>
                                    <select id="payment_status_{{ $order->id }}" class="form-control no-print" style="width:auto; display:inline-block;">
                                        <option value="pending" {{ $order->payment_status == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="paid" {{ $order->payment_status == 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="unpaid" {{ $order->payment_status == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                        <option value="failed" {{ $order->payment_status == 'failed' ? 'selected' : '' }}>Failed</option>
                                    </select>
                                    <button class="btn btn-sm btn-success no-print" onclick="updatePaymentStatus({{ $order->id }})">Update</button>
                                </div>
                                
                                <!-- ✅ Order Status Change (Manual) -->
                                <div style="margin-bottom:15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <p style="margin-bottom: 5px;"><strong>Order Status:</strong> 
                                        <span class="badge bg-{{ $order->order_status == 6 ? 'success' : ($order->order_status == 11 ? 'danger' : 'warning') }}">
                                            {{ $order->status ? $order->status->name : 'N/A' }}
                                        </span>
                                    </p>
                                    @if(isset($orderstatus))
                                    <div class="no-print">
                                        <select id="order_status_{{ $order->id }}" class="form-control" style="width:auto; display:inline-block; margin-right: 5px;">
                                            @foreach($orderstatus as $status)
                                                <option value="{{ $status->id }}" {{ $order->order_status == $status->id ? 'selected' : '' }}>
                                                    {{ $status->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-primary" onclick="updateOrderStatus({{ $order->id }})">
                                            <i class="fa fa-save"></i> Update Status
                                        </button>
                                        @if($order->courier_type)
                                        <br><small class="text-muted" style="margin-top: 5px; display: inline-block;">
                                            <i class="fa fa-truck"></i> Courier: {{ ucfirst($order->courier_type) }}
                                            @if($order->courier_tracking_id)
                                                | Tracking: {{ $order->courier_tracking_id }}
                                            @endif
                                            <br><span style="color: #6c757d; font-size: 11px;">(Auto-update from courier every 10 minutes)</span>
                                        </small>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                                <!-- ✅ END -->

                                <div class="invoice_form">
                                    <p style="font-size:16px;line-height:1.8;color:#222"><strong>Invoice From:</strong></p>
                                    <p style="font-size:16px;line-height:1.8;color:#222">{{$generalsetting->name}}</p>
                                    <p style="font-size:16px;line-height:1.8;color:#222">{{$contact->phone}}</p>
                                    <p style="font-size:16px;line-height:1.8;color:#222">{{$contact->email}}</p>
                            {{-- ⭐ SHOW ORDER NOTE --}}
@if(!empty($order->order_note) || !empty($order->note))
<p style="font-size:16px;line-height:1.8;color:#222">
    <strong>Order Note:</strong> {{ $order->order_note ?? $order->note }}
</p>
@endif
									
                                </div>
                            </td>

                            <td  style="width:60%;float: left;">
                                <div class="invoice-bar" style=" background: #4DBC60; transform: skew(38deg); width: 100%; margin-left: 65px; padding: 20px 60px; ">
                                    <p style="font-size: 30px; color: #fff; transform: skew(-38deg); text-transform: uppercase; text-align: right; font-weight: bold;">Invoice</p>
                                </div>
                                <div class="invoice-bar" style="background: #fff; transform: skew(36deg); width: 72%; margin-left: 182px; padding: 12px 32px; margin-top: 6px;">
                                    <p style="font-size: 15px; color: #222;font-weight:bold; transform: skew(-36deg); text-align: right; padding-right: 18px">Invoice ID : <strong>#{{$order->invoice_id}}</strong></p>
                                    <p style="font-size: 15px; color: #222;font-weight:bold; transform: skew(-36deg); text-align: right; padding-right: 32px">Invoice Date: <strong>{{$order->created_at->format('d-m-y')}}</strong></p>
                                </div>
                                <div class="invoice_to" style="padding-top: 20px;">
                                    <p style="font-size:16px;line-height:1.8;color:#222;text-align: right;"><strong>Invoice To:</strong></p>
                                    <p style="font-size:16px;line-height:1.8;color:#222;text-align: right;">{{$order->shipping?$order->shipping->name:''}}</p>
                                    <p style="font-size:16px;line-height:1.8;color:#222;text-align: right;">{{$order->shipping?$order->shipping->phone:''}}</p>
                                    <p style="font-size:16px;line-height:1.8;color:#222;text-align: right;">{{$order->shipping?$order->shipping->address:''}}</p>
                                    <p style="font-size:16px;line-height:1.8;color:#222;text-align: right;">{{$order->shipping?$order->shipping->area:''}}</p>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table class="table" style="margin-top: 30px;margin-bottom: 0;">
                        <thead style="background: #4DBC60; color: #fff;">
                            <tr>
                                <th>SL</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderdetails as $key=>$value)
                            <tr>
                                <td>{{$loop->iteration}}</td>
                                <td>{{$value->product_name}} 
                                    <br> 
                                @if($value->size) 
                                    <small>Size: {{$value->size->name}}</small><br>
                                @elseif($value->product_size)
                                    <small>Size: {{ $value->product_size }}</small><br>
                                @endif   
                                @php
                                    $displayColor = ($value->color && $value->color->name) ? $value->color->name : ($value->product_color ?: null);
                                @endphp
                                @if($displayColor)
                                    <small>Color: {{ $displayColor }}</small>
                                @endif 
                                </td>
                                <td>৳{{$value->sale_price}}</td>
                                <td>{{$value->qty}}</td>
                                <td>৳{{$value->sale_price*$value->qty}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="invoice-bottom">
                       @php
    $subtotal = $order->orderdetails->sum('sale_price');
    $shipping = $order->shipping_charge;
    $discount = $order->discount;
    $finalTotal = $order->amount;

    // Payment Table থেকে নেওয়া Paid/Advance Amount
    $advancePaid = \App\Models\Payment::where('order_id', $order->id)->sum('amount');

    // Due Amount
    $dueAmount = $finalTotal - $advancePaid;
@endphp

<table class="table" style="width: 300px; float: right; margin-bottom: 30px;">
    <tbody style="background:#f1f9f8">
        <tr>
            <td><strong>SubTotal</strong></td>
            <td><strong>৳{{ $subtotal }}</strong></td>
        </tr>
        <tr>
            <td><strong>Shipping(+)</strong></td>
            <td><strong>৳{{ $shipping }}</strong></td>
        </tr>
        <tr>
            <td><strong>Discount(-)</strong></td>
            <td><strong>৳{{ $discount }}</strong></td>
        </tr>

        <tr style="background:#4DBC60;color:#fff">
            <td><strong>Final Total</strong></td>
            <td><strong>৳{{ $finalTotal }}</strong></td>
        </tr>

        {{-- 🔥 যদি Advance Payment থাকে --}}
        @if($advancePaid > 0 && $advancePaid < $finalTotal)
            <tr>
                <td><strong>Advance Paid</strong></td>
                <td><strong>৳{{ number_format($advancePaid, 2) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Due Amount</strong></td>
                <td><strong>৳{{ number_format($dueAmount, 2) }}</strong></td>
            </tr>
        @endif
    </tbody>
</table>


                        <div class="terms-condition" style="overflow: hidden; width: 100%; text-align: center; padding: 20px 0; border-top: 1px solid #ddd;">
                            <h5 style="font-style: italic;"><a href="{{route('page',['slug'=>'terms-condition'])}}">Terms & Conditions</a></h5>
                            <p style="text-align: center; font-style: italic; font-size: 15px; margin-top: 10px;">* This is a computer generated invoice, does not require any signature.</p>
                        </div>
                    </div>
                </div>

                <div class="history-card">
                    <div class="history-card-title">
                        <h4>Order History</h4>
                        <small>Who changed what and when</small>
                    </div>

                    @if($order->orderHistories && $order->orderHistories->count())
                        <div class="history-timeline">
                            @foreach($order->orderHistories as $history)
                                @php
                                    $historyStatus = $history->status_name ?: ($history->status ? $history->status->name : 'Update');
                                    $historyActor = $history->actor_name ?: ($history->actor ? $history->actor->name : 'System');
                                    $historyActorType = strtolower((string) ($history->actor_type ?? 'system'));
                                    $historyActorLabel = match($historyActorType) {
                                        'admin' => 'Admin',
                                        'reseller' => 'Reseller',
                                        'customer' => 'Customer',
                                        default => 'System',
                                    };
                                    $historyChanges = is_array($history->changes) ? $history->changes : [];
                                @endphp
                                <div class="history-item">
                                    <div class="history-status-dot {{ $loop->odd ? 'history-status-highlight' : '' }}">
                                        {{ \Illuminate\Support\Str::limit($historyStatus, 24, '') }}
                                    </div>
                                    <div class="history-item-card">
                                        <div class="history-item-top">
                                            <div>
                                                <h5>{{ $history->title }}</h5>
                                                <p>{{ $history->description ?: 'Order data was updated.' }}</p>
                                                <span class="history-event-type">{{ str_replace('_', ' ', $history->event_type) }}</span>
                                            </div>
                                            <div class="history-meta">
                                                <strong>{{ $historyActor }}</strong>
                                                <span>{{ $historyActorLabel }}</span>
                                                <span>{{ $history->created_at ? $history->created_at->format('d-m-Y h:i:s A') : 'N/A' }}</span>
                                            </div>
                                        </div>

                                        @if(!empty($historyChanges))
                                            <ul class="history-changes">
                                                @foreach($historyChanges as $field => $change)
                                                    @php
                                                        $oldValue = is_array($change) ? ($change['old'] ?? null) : null;
                                                        $newValue = is_array($change) ? ($change['new'] ?? null) : $change;
                                                        $displayOld = is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue;
                                                        $displayNew = is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : $newValue;
                                                    @endphp
                                                    <li class="history-change-row">
                                                        <strong>{{ $field }}</strong>
                                                        <div class="history-change-values">
                                                            @if($displayOld !== null && $displayOld !== '')
                                                                <del>{{ $displayOld }}</del>
                                                            @endif
                                                            @if($displayNew !== null && $displayNew !== '')
                                                                <ins>{{ $displayNew }}</ins>
                                                            @else
                                                                <span>N/A</span>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="history-empty">
                            No history found for this order yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ✅ JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

<script>
function printFunction() {
    window.print();
}

function updatePaymentStatus(orderId) {
    let status = document.getElementById('payment_status_' + orderId).value;

    fetch('{{ route("admin.order.updatePaymentStatus") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId, payment_status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            toastr.success(data.message, 'Success!');
        } else {
            toastr.error(data.message, 'Error!');
        }
    })
    .catch(err => {
        toastr.error('Something went wrong!', 'Error!');
    });
}

function updateOrderStatus(orderId) {
    let status = document.getElementById('order_status_' + orderId).value;
    
    if (!status) {
        toastr.warning('Please select a status', 'Warning!');
        return;
    }

    // Confirm before changing status
    if (!confirm('Are you sure you want to change the order status? This will manually override any automatic courier status updates.')) {
        return;
    }

    fetch('{{ route("admin.order.updateSingleStatus") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId, order_status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            toastr.success(data.message, 'Success!');
            // Reload page after 1 second to show updated status
            setTimeout(function() {
                location.reload();
            }, 1000);
        } else {
            toastr.error(data.message, 'Error!');
        }
    })
    .catch(err => {
        toastr.error('Something went wrong!', 'Error!');
        console.error(err);
    });
}
</script>
@endsection
