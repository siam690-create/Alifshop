<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Courierapi;
use App\Models\FundTransaction;
use App\Models\OrderDetails;
use App\Models\OrderStatus;
use App\Models\User;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RedXService;

class CheckCourierOrderStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courier:check-status {--limit=50 : Maximum orders to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check order status from Pathao, Steadfast, and RedX courier APIs and update order status automatically';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $deliveredStatusId = $this->resolveStatusId([
            'delivered',
            'completed',
            'complete',
        ], 6);
        $returnedStatusId = $this->resolveStatusId([
            'returned',
            'return',
            'return_to_merchant',
        ]);
        $cancelledStatusId = $this->resolveStatusId([
            'cancelled',
            'canceled',
            'cancel',
        ], 11);
        $partialDeliveredStatusId = $this->resolveStatusId([
            'partial-delivered',
            'partial_delivered',
            'partial delivered',
        ]);
        
        $this->info("🚀 Starting courier order status check...");
        $this->info("📦 Checking up to {$limit} orders");

        $terminalStatuses = array_values(array_filter([
            $deliveredStatusId,
            $returnedStatusId,
            $cancelledStatusId,
            $partialDeliveredStatusId,
        ]));

        // Get all orders that have been sent to courier and are not in a terminal status yet
        $orders = Order::query()
            ->whereNotNull('courier_type')
            ->whereNotNull('courier_tracking_id')
            ->whereIn('courier_type', ['pathao', 'steadfast', 'redx'])
            ->when(!empty($terminalStatuses), function ($query) use ($terminalStatuses) {
                $query->whereNotIn('order_status', $terminalStatuses);
            })
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info("✅ No orders found to check.");
            return Command::SUCCESS;
        }

        $this->info("Found {$orders->count()} orders to check");

        $updated = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($orders as $order) {
            try {
                $status = $this->checkOrderStatus($order);
                
                if ($status === null) {
                    $unchanged++;
                    continue;
                }

                $oldStatus = $order->order_status;
                if ((int) $oldStatus === (int) $status) {
                    $unchanged++;
                    continue;
                }

                $this->applyOrderStatusUpdate($order, (int) $status);

                $updated++;
                $this->info("✅ Order #{$order->invoice_id} ({$order->courier_type}): Status updated from {$oldStatus} to {$status}");

                Log::info("Courier order status auto-updated", [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'courier_type' => $order->courier_type,
                    'tracking_id' => $order->courier_tracking_id,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                ]);

            } catch (\Exception $e) {
                $failed++;
                $this->error("❌ Error checking order #{$order->invoice_id}: " . $e->getMessage());
                
                Log::error("Courier status check failed", [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'courier_type' => $order->courier_type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("   ✅ Updated: {$updated}");
        $this->info("   ⏸️  Unchanged: {$unchanged}");
        $this->info("   ❌ Failed: {$failed}");

        return Command::SUCCESS;
    }

    /**
     * Check order status from courier API
     *
     * @param Order $order
     * @return int|null Returns new order_status or null if no change needed
     */
    private function checkOrderStatus(Order $order)
    {
        if ($order->courier_type === 'pathao') {
            return $this->checkPathaoStatus($order);
        } elseif ($order->courier_type === 'steadfast') {
            return $this->checkSteadfastStatus($order);
        } elseif ($order->courier_type === 'redx') {
            return $this->checkRedXStatus($order);
        }

        return null;
    }

    /**
     * Check Pathao order status
     *
     * @param Order $order
     * @return int|null
     */
    private function checkPathaoStatus(Order $order)
    {
        $pathao_info = Courierapi::where(['status' => 1, 'type' => 'pathao'])->first();

        if (!$pathao_info || empty($pathao_info->token)) {
            Log::warning("Pathao not configured or token missing");
            return null;
        }

        $consignmentId = $order->courier_tracking_id ?? $order->consignment_id;
        
        if (empty($consignmentId)) {
            Log::warning("Pathao consignment_id missing for order", ['order_id' => $order->id]);
            return null;
        }

        try {
            // Clean up URL
            $baseUrl = rtrim($pathao_info->url ?? 'https://api-hermes.pathao.com', '/');
            $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);

            // Pathao API: Get Order Info
            // Endpoint: /aladdin/api/v1/orders/{consignment_id}/info
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pathao_info->token,
                'Accept' => 'application/json',
            ])->get($baseUrl . '/aladdin/api/v1/orders/' . $consignmentId . '/info');

            if (!$response->successful()) {
                // Token might be expired, try to refresh
                if ($response->status() === 401 && !empty($pathao_info->client_id) && !empty($pathao_info->client_secret)) {
                    Log::info("Pathao token expired, attempting refresh", ['order_id' => $order->id]);
                    
                    try {
                        $tokenResponse = $this->refreshPathaoToken($pathao_info);
                        if ($tokenResponse && isset($tokenResponse['access_token'])) {
                            $pathao_info->token = $tokenResponse['access_token'];
                            $pathao_info->save();
                            
                            // Retry the request with new token
                            $response = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $pathao_info->token,
                                'Accept' => 'application/json',
                            ])->get($baseUrl . '/aladdin/api/v1/orders/' . $consignmentId . '/info');
                            
                            if (!$response->successful()) {
                                return null;
                            }
                        } else {
                            return null;
                        }
                    } catch (\Exception $e) {
                        Log::error("Pathao token refresh failed", ['error' => $e->getMessage()]);
                        return null;
                    }
                } else {
                    return null;
                }
            }

            $data = $response->json();
            
            if (!isset($data['data']['order_status_slug'])) {
                return null;
            }

            $pathaoStatus = strtolower($data['data']['order_status_slug'] ?? '');

            return $this->mapCourierStatusToOrderStatus($pathaoStatus);

        } catch (\Exception $e) {
            Log::error("Pathao status check error", [
                'order_id' => $order->id,
                'consignment_id' => $consignmentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check Steadfast order status
     *
     * @param Order $order
     * @return int|null
     */
    private function checkSteadfastStatus(Order $order)
    {
        $steadfast_info = Courierapi::where(['status' => 1, 'type' => 'steadfast'])->first();

        if (!$steadfast_info || empty($steadfast_info->api_key) || empty($steadfast_info->secret_key)) {
            Log::warning("Steadfast not configured");
            return null;
        }

        // Steadfast supports checking by consignment_id, invoice, or tracking_code
        $consignmentId = $order->courier_tracking_id ?? $order->consignment_id;
        $invoiceId = $order->invoice_id;

        try {
            // Clean up URL
            $baseUrl = rtrim($steadfast_info->url ?? 'https://portal.packzy.com/api/v1', '/');

            // Try consignment_id first, then invoice
            $endpoint = null;
            $identifier = null;

            if (!empty($consignmentId)) {
                $endpoint = '/status_by_cid/' . $consignmentId;
                $identifier = $consignmentId;
            } elseif (!empty($invoiceId)) {
                $endpoint = '/status_by_invoice/' . $invoiceId;
                $identifier = $invoiceId;
            } else {
                Log::warning("Steadfast: No tracking ID or invoice ID found", ['order_id' => $order->id]);
                return null;
            }

            $response = Http::withHeaders([
                'Api-Key' => $steadfast_info->api_key,
                'Secret-Key' => $steadfast_info->secret_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get($baseUrl . $endpoint);

            if (!$response->successful()) {
                Log::warning("Steadfast API error", [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            
            if (!isset($data['delivery_status'])) {
                return null;
            }

            $steadfastStatus = strtolower($data['delivery_status'] ?? '');

            return $this->mapCourierStatusToOrderStatus($steadfastStatus);

        } catch (\Exception $e) {
            Log::error("Steadfast status check error", [
                'order_id' => $order->id,
                'consignment_id' => $consignmentId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Refresh Pathao access token using username/password
     *
     * @param Courierapi $pathao_info
     * @return array|null
     */
    private function refreshPathaoToken(Courierapi $pathao_info)
    {
        try {
            $baseUrl = rtrim($pathao_info->url ?? 'https://api-hermes.pathao.com', '/');
            $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);

            // Generate new token with username/password
            if (!empty($pathao_info->username) && !empty($pathao_info->password)) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($baseUrl . '/aladdin/api/v1/issue-token', [
                    'client_id' => $pathao_info->client_id,
                    'client_secret' => $pathao_info->client_secret,
                    'grant_type' => 'password',
                    'username' => $pathao_info->username,
                    'password' => $pathao_info->password,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Pathao token refresh error", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check RedX order status
     *
     * @param Order $order
     * @return int|null
     */
    private function checkRedXStatus(Order $order)
    {
        $redx_info = Courierapi::where(['status' => 1, 'type' => 'redx'])->first();

        if (!$redx_info || empty($redx_info->token)) {
            Log::warning("RedX not configured or token missing");
            return null;
        }

        $trackingId = $order->courier_tracking_id ?? $order->consignment_id;
        
        if (empty($trackingId)) {
            Log::warning("RedX tracking_id missing for order", ['order_id' => $order->id]);
            return null;
        }

        try {
            $redxService = new RedXService();
            $parcelDetails = $redxService->getParcelDetails($trackingId);
            
            if (!$parcelDetails || !isset($parcelDetails['parcel']['status'])) {
                return null;
            }

            $redxStatus = strtolower($parcelDetails['parcel']['status'] ?? '');

            return $this->mapCourierStatusToOrderStatus($redxStatus);

        } catch (\Exception $e) {
            Log::error("RedX status check error", [
                'order_id' => $order->id,
                'tracking_id' => $trackingId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function mapCourierStatusToOrderStatus(string $courierStatus): ?int
    {
        $normalizedStatus = str_replace(['_', '-'], ' ', strtolower(trim($courierStatus)));

        $statusGroups = [
            [
                'match' => [
                    'delivered',
                    'completed',
                    'delivered approval pending',
                ],
                'target' => ['delivered', 'completed', 'complete'],
                'fallback' => 6,
            ],
            [
                'match' => [
                    'partial delivered',
                    'partially delivered',
                    'partial delivery',
                ],
                'target' => ['partial-delivered', 'partial_delivered', 'partial delivered'],
                'fallback' => null,
            ],
            [
                'match' => [
                    'returned',
                    'return',
                    'return to merchant',
                    'merchant returned',
                ],
                'target' => ['returned', 'return', 'return_to_merchant'],
                'fallback' => $this->resolveStatusId(['cancelled', 'canceled', 'cancel'], 11),
            ],
            [
                'match' => [
                    'cancelled',
                    'canceled',
                    'cancelled approval pending',
                ],
                'target' => ['cancelled', 'canceled', 'cancel'],
                'fallback' => 11,
            ],
        ];

        foreach ($statusGroups as $group) {
            if (in_array($normalizedStatus, $group['match'], true)) {
                return $this->resolveStatusId($group['target'], $group['fallback']);
            }
        }

        return null;
    }

    private function resolveStatusId(array $aliases, ?int $fallback = null): ?int
    {
        foreach ($aliases as $alias) {
            $normalizedAlias = strtolower(trim($alias));
            $status = OrderStatus::query()
                ->whereRaw('LOWER(slug) = ?', [$normalizedAlias])
                ->orWhereRaw('LOWER(name) = ?', [$normalizedAlias])
                ->first();

            if ($status) {
                return (int) $status->id;
            }
        }

        return $fallback;
    }

    private function applyOrderStatusUpdate(Order $order, int $newStatus): void
    {
        $oldStatus = (int) $order->order_status;

        $order->order_status = $newStatus;
        $order->save();

        $this->handleStockChange($order, $oldStatus, $newStatus);

        $deliveredStatusId = $this->resolveStatusId(['delivered', 'completed', 'complete'], 6);
        if ($newStatus === (int) $deliveredStatusId && $oldStatus !== (int) $deliveredStatusId) {
            $this->recordDeliveredSideEffects($order);
        }
    }

    private function handleStockChange(Order $order, int $oldStatus, int $newStatus): void
    {
        $activeStatusIds = array_values(array_unique(array_filter([
            $this->resolveStatusId(['pending'], 1),
            $this->resolveStatusId(['processing'], 2),
            $this->resolveStatusId(['on-the-way', 'on_the_way', 'on the way'], 3),
            $this->resolveStatusId(['in-courier', 'in_courier', 'in courier'], 5),
            $this->resolveStatusId(['delivered', 'completed', 'complete'], 6),
            $this->resolveStatusId(['partial-delivered', 'partial_delivered', 'partial delivered']),
        ])));

        if (in_array($newStatus, $activeStatusIds, true) && !in_array($oldStatus, $activeStatusIds, true)) {
            $details = OrderDetails::where('order_id', $order->id)
                ->with('product:id,stock')
                ->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = max(0, $row->product->stock - $row->qty);
                    $row->product->save();
                }
            }
        }

        $cancelLikeStatuses = array_values(array_unique(array_filter([
            $this->resolveStatusId(['cancelled', 'canceled', 'cancel'], 11),
            $this->resolveStatusId(['returned', 'return', 'return_to_merchant']),
        ])));

        if (in_array($newStatus, $cancelLikeStatuses, true) && in_array($oldStatus, $activeStatusIds, true)) {
            $details = OrderDetails::where('order_id', $order->id)
                ->with('product:id,stock')
                ->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = $row->product->stock + $row->qty;
                    $row->product->save();
                }
            }
        }
    }

    private function recordDeliveredSideEffects(Order $order): void
    {
        $existingSaleFund = FundTransaction::where('source', 'sale')
            ->where('source_id', $order->id)
            ->exists();

        if (!$existingSaleFund) {
            FundTransaction::create([
                'direction' => 'in',
                'source' => 'sale',
                'source_id' => $order->id,
                'amount' => $order->amount,
                'note' => 'Order complete (#' . $order->invoice_id . ') - Courier auto sync',
                'created_by' => 1,
            ]);
        }

        $this->distributeVendorEarnings($order);
        $this->creditResellerWallet($order);
    }

    private function distributeVendorEarnings(Order $order): void
    {
        $order->loadMissing('orderdetails.product.vendor');

        foreach ($order->orderdetails as $item) {
            if (!empty($item->vendor_paid_at)) {
                continue;
            }

            $product = $item->product;
            $vendor = optional($product)->vendor;
            $vendorId = optional($vendor)->id;

            if (!$vendorId) {
                continue;
            }

            $commissionRate = isset($vendor->commission_rate) ? $vendor->commission_rate : config('app.vendor_commission', 10);
            $lineTotal = (float) ($item->sale_price ?? 0) * (float) ($item->qty ?? 0);
            $adminCommission = round($lineTotal * ($commissionRate / 100), 2);
            $vendorEarning = max(0, round($lineTotal - $adminCommission, 2));

            $item->update([
                'vendor_id' => $vendorId,
                'commission_rate' => $commissionRate,
                'admin_commission' => $adminCommission,
                'vendor_earning' => $vendorEarning,
                'vendor_paid_at' => now(),
            ]);

            $wallet = VendorWallet::firstOrCreate(['vendor_id' => $vendorId]);
            $wallet->balance += $vendorEarning;
            $wallet->total_earned += $vendorEarning;
            $wallet->save();

            VendorWalletTransaction::create([
                'vendor_id' => $vendorId,
                'type' => 'earning',
                'status' => 'completed',
                'amount' => $vendorEarning,
                'source_type' => 'order',
                'source_id' => $item->id,
                'note' => 'Order #' . $order->invoice_id . ' item earning',
            ]);

            if ($adminCommission > 0) {
                $hasCommissionFund = FundTransaction::where('source', 'vendor_commission')
                    ->where('source_id', $order->id)
                    ->where('note', 'like', '%' . $item->product_name . '%')
                    ->exists();

                if (!$hasCommissionFund) {
                    FundTransaction::create([
                        'direction' => 'in',
                        'source' => 'vendor_commission',
                        'source_id' => $order->id,
                        'amount' => $adminCommission,
                        'note' => 'Vendor commission from Order #' . $order->invoice_id . ' - Product: ' . $item->product_name,
                        'created_by' => 1,
                    ]);
                }
            }
        }
    }

    private function creditResellerWallet(Order $order): void
    {
        if (!$order->reseller_profit || $order->reseller_profit <= 0 || $order->reseller_wallet_credited) {
            return;
        }

        $order->loadMissing('customer');

        $resellerUser = null;
        if ($order->user_id) {
            $candidate = User::find($order->user_id);
            if ($candidate && ($candidate->hasRole('reseller') || (isset($candidate->role) && strtolower($candidate->role) === 'reseller'))) {
                $resellerUser = $candidate;
            }
        }

        if (!$resellerUser && optional($order->customer)->email) {
            $resellerUser = User::where('email', $order->customer->email)
                ->where(function ($query) {
                    $query->where('role', 'reseller')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'reseller');
                        });
                })
                ->first();
        }

        if (!$resellerUser) {
            return;
        }

        $resellerUser->wallet_balance = (float) ($resellerUser->wallet_balance ?? 0) + (float) $order->reseller_profit;
        $resellerUser->save();

        $order->reseller_wallet_credited = true;
        $order->save();
    }
}
