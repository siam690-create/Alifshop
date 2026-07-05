<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ResellerInvoiceService;
use Illuminate\Console\Command;

class GenerateResellerInvoices extends Command
{
    protected $signature = 'reseller-invoices:generate {--reseller= : Generate invoice for one reseller user ID}';

    protected $description = 'Generate due reseller payout invoices from completed/returned orders';

    public function handle(ResellerInvoiceService $invoiceService): int
    {
        $resellerId = $this->option('reseller');

        if ($resellerId) {
            $reseller = User::where('role', 'reseller')->find($resellerId);

            if (!$reseller) {
                $this->error('Reseller not found.');
                return self::FAILURE;
            }

            $invoice = $invoiceService->generateFor($reseller);
            $this->info($invoice ? "Invoice {$invoice->invoice_no} generated." : 'No due orders found for this reseller.');

            return self::SUCCESS;
        }

        $created = $invoiceService->generateDueInvoices();
        $this->info("Generated {$created->count()} reseller invoice(s).");

        return self::SUCCESS;
    }
}
