<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Print</title>
    <link rel="stylesheet" href="{{asset('public/frontEnd/css/bootstrap.min.css')}}" />
    <link rel="stylesheet" href="{{asset('public/frontEnd/css/all.min.css')}}" />
    <style>
        body {
            background: #f1f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .invoice-wrapper {
            background: #fff;
            max-width: 850px;
            margin: 25px auto;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .invoice-header img {
            max-width: 200px;
        }
        .invoice-header h4 {
            margin: 0;
            color: #333;
        }
        .invoice-title {
            background: #4dbc60;
            color: #fff;
            text-align: center;
            padding: 10px 0;
            margin-top: 20px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 24px;
        }
        table th, table td {
            vertical-align: middle !important;
        }
        .payment-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .payment-info h6 {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .summary-table {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .summary-table tr td {
            padding: 8px 10px;
        }
        .summary-table tr:last-child {
            background: #4dbc60;
            color: #fff;
            font-weight: bold;
        }
        .terms {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff !important;
            }
            .invoice-wrapper {
                box-shadow: none !important;
                margin: 0;
                border-radius: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container text-center mt-3">
    <button onclick="printFunction()" class="no-print btn btn-success btn-sm">
        <i class="fa fa-print"></i> Print
    </button>
</div>

@foreach($orders as $order)
<div class="invoice-wrapper">
    <div class="invoice-header d-flex justify-content-between align-items-start">
        <div>
            <img src="{{asset($generalsetting->white_logo)}}" alt="Logo">
            <p class="mt-2 mb-1"><strong>{{$generalsetting->name}}</strong></p>
            <p class="mb-1">{{$contact->phone}}</p>
            <p class="mb-1">{{$contact->email}}</p>
            <p class="mb-1">{{$contact->address}}</p>
        </div>
        <div class="text-end">
            <h4>Invoice #{{$order->invoice_id}}</h4>
            <p>Date: <strong>{{$order->created_at->format('d M, Y')}}</strong></p>
            <p>Status: <span class="badge bg-success">{{ ucfirst($order->order_status_text ?? 'Processing') }}</span></p>
        </div>
    </div>

    <div class="invoice-title">Invoice</div>

    <div class="row mt-4">
        <div class="col-md-6">
            <h6><strong>Bill From:</strong></h6>
            <p class="mb-1">{{$generalsetting->name}}</p>
            <p class="mb-1">{{$contact->phone}}</p>
            <p class="mb-1">{{$contact->email}}</p>
            <p>{{$contact->address}}</p>
        </div>
        <div class="col-md-6 text-end">
            <h6><strong>Bill To:</strong></h6>
            <p class="mb-1">{{$order->shipping?$order->shipping->name:''}}</p>
            <p class="mb-1">{{$order->shipping?$order->shipping->phone:''}}</p>
            <p class="mb-1">{{$order->shipping?$order->shipping->address:''}}</p>
            <p>{{$order->shipping?$order->shipping->area:''}}</p>
        </div>
    </div>

    <table class="table table-bordered mt-4">
        <thead class="table-success">
            <tr>
                <th>SL</th>
                <th>Product</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->orderdetails as $key => $value)
            <tr>
                <td>{{$loop->iteration}}</td>
                <td>
                    {{$value->product_name}}
                    @if($value->product_size) <br><small>Size: {{$value->product_size}}</small> @endif
                    @if($value->product_color) <br><small>Color: {{$value->product_color}}</small> @endif
                </td>
                <td>৳{{$value->sale_price}}</td>
                <td>{{$value->qty}}</td>
                <td>৳{{$value->sale_price * $value->qty}}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ✅ Payment Info Box -->
    <div class="payment-info">
        <h6><i class="fa fa-credit-card"></i> Payment Information</h6>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Payment Gateway:</strong> 
                    <span class="text-uppercase">{{$order->payment_gateway ?? ($order->payment?$order->payment->payment_method:'N/A')}}</span>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p><strong>Payment Status:</strong> 
                    @php 
                        $status = $order->payment_status ?? ($order->payment?$order->payment->payment_status:'pending'); 
                    @endphp
                    @if($status == 'paid')
                        <span class="badge bg-success">Paid</span>
                    @elseif($status == 'pending')
                        <span class="badge bg-warning text-dark">Pending</span>
                    @elseif($status == 'failed')
                        <span class="badge bg-danger">Failed</span>
                    @else
                        <span class="badge bg-secondary text-white">{{ ucfirst($status) }}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Summary Table -->
    <table class="table summary-table">
        <tbody>
            <tr>
                <td><strong>Subtotal</strong></td>
                <td>৳{{$order->orderdetails->sum('sale_price')}}</td>
            </tr>
            <tr>
                <td><strong>Shipping (+)</strong></td>
                <td>৳{{$order->shipping_charge}}</td>
            </tr>
            <tr>
                <td><strong>Discount (-)</strong></td>
                <td>৳{{$order->discount}}</td>
            </tr>
            <tr>
                <td><strong>Final Total</strong></td>
                <td>৳{{$order->amount}}</td>
            </tr>
        </tbody>
    </table>

    <div class="terms">
        <p><a href="{{route('page',['slug'=>'terms-condition'])}}">Terms & Conditions</a></p>
        <p>* This is a computer-generated invoice and does not require any signature.</p>
    </div>
</div>
@endforeach

<script>
function printFunction() {
    window.print();
}
</script>
</body>
</html>
