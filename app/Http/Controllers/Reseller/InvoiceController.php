<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerInvoice;
use App\Services\ResellerInvoiceService;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function index()
    {
        $user = Auth::guard('admin')->user();
        $invoices = ResellerInvoice::where('user_id', $user->id)
            ->withCount('items')
            ->latest()
            ->paginate(15);

        return view('reseller.invoices.index', compact('user', 'invoices'));
    }

    public function show(ResellerInvoice $invoice)
    {
        $user = Auth::guard('admin')->user();
        $this->authorizeInvoice($invoice);
        $invoice->load(['items', 'user']);

        return view('reseller.invoices.show', compact('user', 'invoice'));
    }

    public function csv(ResellerInvoice $invoice, ResellerInvoiceService $service)
    {
        $this->authorizeInvoice($invoice);

        return $service->csvResponse($invoice);
    }

    private function authorizeInvoice(ResellerInvoice $invoice): void
    {
        abort_unless((int) $invoice->user_id === (int) Auth::guard('admin')->id(), 403);
    }
}
