<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Payment;
use App\Models\DigitalDownload;
use App\Models\GeneralSetting;
use App\Models\Contact;

class ResellerOrderSuccessController extends Controller
{
    /**
     * Display reseller order success page.
     *
     * @param int $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show($id)
    {
        $user = Auth::guard('admin')->user();

        // Verify reseller
        if (!$user || (!$user->hasRole('reseller') && $user->role !== 'reseller')) {
            return redirect()->route('reseller.dashboard');
        }

        $order = Order::with(['customer', 'orderdetails.product', 'orderdetails.color', 'orderdetails.size', 'status', 'shipping'])
            ->where('id', $id)
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhereHas('customer', function($q) use ($user) {
                          $q->where('email', $user->email);
                      });
            })
            ->whereNotNull('reseller_profit')
            ->first();

        if (!$order) {
            return redirect()->route('reseller.orders')->with('error', 'Order not found');
        }

        // Get payment info
        $payment = Payment::where('order_id', $order->id)->orderBy('id', 'desc')->first();

        $gateway_status = $payment ? strtolower(trim($payment->payment_status)) : ''; 
        $payment_method = $payment ? strtolower(trim($payment->payment_method)) : strtolower(trim($order->payment_method ?? ''));
        
        $admin_status = strtolower(trim($order->payment_status ?? ''));
        $order_status = $order->status ? strtolower(trim($order->status->name ?? '')) : '';

        $grand_total = $order->amount;
        $paid_amount = 0;

        // Get paid amount from payment record
        if ($payment && !in_array($gateway_status, ['failed', 'cancel', 'cancelled', 'rejected'])) {
            $paid_amount = $payment->amount;
        }

        // COD fix
        $is_cod = in_array($payment_method, ['cod', 'cash', 'cash_on_delivery', 'hand cash', 'hand_cash']);

        $is_order_completed =
            in_array($order_status, ['completed', 'delivered']) ||
            in_array($admin_status, ['completed', 'delivered']);

        if ($is_cod && !$is_order_completed) {
            if ($paid_amount >= $grand_total) {
                $paid_amount = 0;
            }
        }

        // Admin priority
        if ($is_order_completed) {
            $paid_amount = $grand_total;
        } elseif (($paid_amount == 0 || !$payment) && in_array($admin_status, ['paid', 'success', 'approved'])) {
            $paid_amount = $grand_total;
        }

        // Due
        $due_amount = max(0, $grand_total - $paid_amount);
        $subtotal = ($order->amount + $order->discount) - $order->shipping_charge;

        // Digital downloads (only if fully paid)
        $is_fully_paid = ($paid_amount >= $grand_total);
        $downloads = $is_fully_paid ? DigitalDownload::where('order_id', $order->id)->get() : collect();

        $generalsetting = GeneralSetting::first();
        $contact = Contact::first();

        return view('reseller.order_success', compact(
            'order',
            'payment',
            'grand_total',
            'paid_amount',
            'due_amount',
            'subtotal',
            'is_fully_paid',
            'downloads',
            'generalsetting',
            'contact',
            'user'
        ));
    }
}
