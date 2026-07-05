<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundTransaction;
use App\Models\ResellerInvoice;
use App\Models\User;
use App\Services\ResellerInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Toastr;

class ResellerInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = ResellerInvoice::with('user')->withCount('items')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reseller_id')) {
            $query->where('user_id', $request->reseller_id);
        }

        $invoices = $query->paginate(20)->appends($request->query());
        $resellers = User::where('role', 'reseller')->orderBy('name')->get(['id', 'name', 'shop_name', 'reseller_payout_cycle']);

        return view('backEnd.reseller.invoices.index', compact('invoices', 'resellers'));
    }

    public function generate(Request $request, ResellerInvoiceService $service)
    {
        $request->validate([
            'reseller_id' => 'nullable|integer|exists:users,id',
            'force' => 'nullable|boolean',
        ]);

        if ($request->filled('reseller_id')) {
            $reseller = User::findOrFail($request->reseller_id);
            $invoice = $service->generateFor($reseller, now(), (bool) $request->boolean('force'));
            $invoice
                ? Toastr::success('Invoice generated successfully.', 'Success')
                : Toastr::warning('No eligible completed order found for this reseller.', 'No Invoice');
        } else {
            $created = $service->generateDueInvoices();
            Toastr::success($created . ' invoice(s) generated.', 'Success');
        }

        return back();
    }

    public function show(ResellerInvoice $invoice)
    {
        $invoice->load(['items.order', 'user']);

        return view('backEnd.reseller.invoices.show', compact('invoice'));
    }

    public function csv(ResellerInvoice $invoice, ResellerInvoiceService $service)
    {
        return $service->csvResponse($invoice);
    }

    public function markPaid(Request $request, ResellerInvoice $invoice)
    {
        if ($invoice->status !== 'pending') {
            Toastr::error('Invoice already processed.', 'Error');
            return back();
        }

        DB::transaction(function () use ($invoice, $request) {
            $invoice->load('user');
            $reseller = User::whereKey($invoice->user_id)->lockForUpdate()->firstOrFail();

            $netPayable = (float) $invoice->net_payable;
            $walletDelta = $netPayable >= 0 ? (-1 * $netPayable) : $netPayable;
            $reseller->wallet_balance = round(((float) ($reseller->wallet_balance ?? 0)) + $walletDelta, 2);
            $reseller->save();

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by' => Auth::id(),
                'note' => $request->note,
            ]);

            if ($netPayable > 0) {
                FundTransaction::create([
                    'direction' => 'out',
                    'source' => 'reseller_invoice',
                    'source_id' => $invoice->id,
                    'amount' => $netPayable,
                    'note' => 'Reseller invoice paid - ' . $invoice->invoice_no,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        Toastr::success('Invoice marked as paid.', 'Success');
        return back();
    }
}
