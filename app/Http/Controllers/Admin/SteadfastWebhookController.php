<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courierapi;
use App\Models\FundTransaction;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderStatus;
use App\Models\SmsGateway;
use App\Models\User;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SteadfastWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('Steadfast Webhook Received', [
                'payload' => $payload,
                'headers' => $request->headers->all(),
            ]);

            $config = Courierapi::where('type', 'steadfast')->first();
            $expectedToken = trim((string) optional($config)->token);

            if ($expectedToken !== '' && !$this->hasValidToken($request, $expectedToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized webhook token',
                ], 401);
            }

            $statusText = strtolower(trim((string) (
                $request->input('delivery_status')
                ?? $request->input('status')
                ?? $request->input('TripStatus')
                ?? $request->input('trip_status')
                ?? data_get($payload, 'data.delivery_status')
                ?? data_get($payload, 'data.status')
                ?? data_get($payload, 'data.TripStatus')
                ?? ''
            )));

            if ($statusText === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required field: status',
                ], 400);
            }

            $order = $this->findOrder($request, $payload);

            if (!$order) {
                Log::warning('Steadfast Webhook: Order not found', [
                    'payload' => $payload,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $newStatus = $this->mapWebhookToOrderStatus($statusText);

            if ($newStatus === null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook received, no status change required',
                ], 200);
            }

            $oldStatus = (int) $order->order_status;
            $newStatus = (int) $newStatus;

            if ($oldStatus !== $newStatus) {
                $order->order_status = $newStatus;
                $order->save();

                $this->handleStockChange($order, $oldStatus, $newStatus);

                $deliveredStatusId = $this->resolveStatusId(['delivered', 'completed', 'complete'], 6);
                if ($newStatus === (int) $deliveredStatusId && $oldStatus !== (int) $deliveredStatusId) {
                    FundTransaction::create([
                        'direction' => 'in',
                        'source' => 'sale',
                        'source_id' => $order->id,
                        'amount' => $order->amount,
                        'note' => 'Order complete via Steadfast webhook (#' . $order->invoice_id . ')',
                        'created_by' => 1,
                    ]);

                    $this->distributeVendorEarnings($order);
                    $this->creditResellerWallet($order);
                }

                $this->sendStatusUpdateSMS($order, $newStatus);
            }

            Log::info('Steadfast Webhook: Order processed successfully', [
                'order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status_text' => $statusText,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Steadfast Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    private function hasValidToken(Request $request, string $expectedToken): bool
    {
        $candidates = array_filter([
            trim((string) $request->bearerToken()),
            trim((string) $request->header('X-Webhook-Token')),
            trim((string) $request->header('Webhook-Token')),
            trim((string) $request->input('token')),
            trim((string) $request->input('secret')),
        ]);

        return in_array($expectedToken, $candidates, true);
    }

    private function findOrder(Request $request, array $payload): ?Order
    {
        $candidates = array_values(array_unique(array_filter([
            trim((string) $request->input('invoice')),
            trim((string) $request->input('invoice_id')),
            trim((string) $request->input('consignment_id')),
            trim((string) $request->input('tracking_code')),
            trim((string) $request->input('ConsignmentReference')),
            trim((string) $request->input('JobNumber')),
            trim((string) data_get($payload, 'data.invoice')),
            trim((string) data_get($payload, 'data.invoice_id')),
            trim((string) data_get($payload, 'data.consignment_id')),
            trim((string) data_get($payload, 'data.tracking_code')),
            trim((string) data_get($payload, 'data.ConsignmentReference')),
            trim((string) data_get($payload, 'data.JobNumber')),
        ], fn ($value) => $value !== '')));

        if (empty($candidates)) {
            return null;
        }

        $order = Order::query()
            ->whereIn('invoice_id', $candidates)
            ->orWhereIn('courier_tracking_id', $candidates)
            ->orWhereIn('consignment_id', $candidates)
            ->first();

        if ($order) {
            $trackingValue = data_get($payload, 'data.ConsignmentReference')
                ?: data_get($payload, 'data.JobNumber')
                ?: $request->input('ConsignmentReference')
                ?: $request->input('JobNumber')
                ?: $request->input('consignment_id')
                ?: $request->input('tracking_code');

            $trackingValue = trim((string) $trackingValue);
            if ($trackingValue !== '') {
                if ((string) $order->courier_tracking_id !== $trackingValue) {
                    $order->courier_tracking_id = $trackingValue;
                }
                if ((string) $order->consignment_id !== $trackingValue) {
                    $order->consignment_id = $trackingValue;
                }
                if (empty($order->courier_type)) {
                    $order->courier_type = 'steadfast';
                }
                if (empty($order->courier_sent_at)) {
                    $order->courier_sent_at = now();
                }
                $order->save();
            }
        }

        return $order;
    }

    private function mapWebhookToOrderStatus(string $statusText): ?int
    {
        $statusText = str_replace(['_', '-'], ' ', strtolower(trim($statusText)));

        $statusGroups = [
            [
                'match' => ['delivered', 'successfully delivered'],
                'target' => ['delivered', 'completed', 'complete'],
                'fallback' => 6,
            ],
            [
                'match' => ['partial delivered', 'partially delivered', 'partial'],
                'target' => ['partial-delivered', 'partial_delivered', 'partial delivered'],
                'fallback' => null,
            ],
            [
                'match' => ['cancelled', 'canceled', 'cancel', 'delivery cancelled'],
                'target' => ['cancelled', 'canceled', 'cancel'],
                'fallback' => 11,
            ],
            [
                'match' => ['returned', 'return', 'return to merchant', 'delivery return', 'delivery returned'],
                'target' => ['returned', 'return', 'return_to_merchant'],
                'fallback' => null,
            ],
            [
                'match' => ['hold', 'on hold'],
                'target' => ['processing'],
                'fallback' => 2,
            ],
            [
                'match' => ['pending', 'parcel pending'],
                'target' => ['pending'],
                'fallback' => 1,
            ],
            [
                'match' => ['in review', 'parcel received', 'received at warehouse', 'received'],
                'target' => ['in-courier', 'in_courier', 'in courier'],
                'fallback' => 5,
            ],
            [
                'match' => ['distributed', 'dispatch', 'rider assigned', 'out for delivery', 'assigned for delivery'],
                'target' => ['on-the-way', 'on_the_way', 'on the way'],
                'fallback' => 3,
            ],
            [
                'match' => ['transit', 'in transit', 'picked up', 'pickedup'],
                'target' => ['in-courier', 'in_courier', 'in courier'],
                'fallback' => 5,
            ],
        ];

        foreach ($statusGroups as $group) {
            if (in_array($statusText, $group['match'], true)) {
                return $this->resolveStatusId($group['target'], $group['fallback']);
            }
        }

        return null;
    }

    private function handleStockChange(Order $order, int $oldStatus, int $newStatus): void
    {
        $activeStatuses = array_values(array_unique(array_filter([
            $this->resolveStatusId(['pending'], 1),
            $this->resolveStatusId(['processing'], 2),
            $this->resolveStatusId(['on-the-way', 'on_the_way', 'on the way'], 3),
            $this->resolveStatusId(['in-courier', 'in_courier', 'in courier'], 5),
            $this->resolveStatusId(['delivered', 'completed', 'complete'], 6),
            $this->resolveStatusId(['partial-delivered', 'partial_delivered', 'partial delivered']),
        ])));

        if (in_array($newStatus, $activeStatuses, true) && !in_array($oldStatus, $activeStatuses, true)) {
            $details = OrderDetails::where('order_id', $order->id)->with('product:id,stock')->get();

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

        if (in_array($newStatus, $cancelLikeStatuses, true) && in_array($oldStatus, $activeStatuses, true)) {
            $details = OrderDetails::where('order_id', $order->id)->with('product:id,stock')->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = $row->product->stock + $row->qty;
                    $row->product->save();
                }
            }
        }
    }

    private function distributeVendorEarnings(Order $order): void
    {
        $details = $order->orderdetails()
            ->with([
                'product:id,vendor_id,name',
                'product.vendor:id,commission_rate',
            ])
            ->get();

        foreach ($details as $item) {
            $product = $item->product;
            if (!$product || !$product->vendor_id || $item->vendor_paid_at) {
                continue;
            }

            $vendor = $product->vendor;
            if (!$vendor) {
                continue;
            }

            $commissionRate = $vendor->commission_rate ?? config('app.vendor_commission', 10);
            $lineTotal = (float) ($item->sale_price ?? 0) * (float) ($item->qty ?? 0);
            $adminCommission = round($lineTotal * ($commissionRate / 100), 2);
            $vendorEarning = max(0, round($lineTotal - $adminCommission, 2));

            $item->update([
                'vendor_id' => $product->vendor_id,
                'commission_rate' => $commissionRate,
                'admin_commission' => $adminCommission,
                'vendor_earning' => $vendorEarning,
                'vendor_paid_at' => now(),
            ]);

            $wallet = VendorWallet::firstOrCreate(['vendor_id' => $product->vendor_id]);
            $wallet->balance += $vendorEarning;
            $wallet->total_earned += $vendorEarning;
            $wallet->save();

            VendorWalletTransaction::create([
                'vendor_id' => $product->vendor_id,
                'type' => 'earning',
                'status' => 'completed',
                'amount' => $vendorEarning,
                'source_type' => 'order',
                'source_id' => $item->id,
                'note' => 'Order #' . $order->invoice_id . ' item earning (Steadfast)',
            ]);

            if ($adminCommission > 0) {
                FundTransaction::create([
                    'direction' => 'in',
                    'source' => 'vendor_commission',
                    'source_id' => $order->id,
                    'amount' => $adminCommission,
                    'note' => 'Vendor commission from Order #' . $order->invoice_id . ' - Product: ' . $item->product_name . ' (Steadfast)',
                    'created_by' => 1,
                ]);
            }
        }
    }

    private function creditResellerWallet(Order $order): void
    {
        if (!$order->reseller_profit || $order->reseller_profit <= 0 || $order->reseller_wallet_credited) {
            return;
        }

        $resellerUser = null;

        if ($order->user_id) {
            $candidate = User::find($order->user_id);
            if ($candidate && ($candidate->hasRole('reseller') || (isset($candidate->role) && strtolower((string) $candidate->role) === 'reseller'))) {
                $resellerUser = $candidate;
            }
        }

        if (!$resellerUser && $order->customer && $order->customer->email) {
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

        $resellerUser->wallet_balance = ($resellerUser->wallet_balance ?? 0) + $order->reseller_profit;
        $resellerUser->save();

        $order->reseller_wallet_credited = true;
        $order->save();
    }

    private function sendStatusUpdateSMS(Order $order, int $newStatus): void
    {
        try {
            $smsGateway = SmsGateway::where('status', 1)->first();
            $siteSetting = GeneralSetting::first();
            $orderStatus = OrderStatus::find($newStatus);

            if ($smsGateway && $order->customer && $orderStatus) {
                $data = [
                    'api_key' => $smsGateway->api_key,
                    'number' => $order->customer->phone,
                    'type' => 'text',
                    'senderid' => $smsGateway->serderid,
                    'message' => 'Dear ' . $order->customer->name . ', your order #' . $order->invoice_id . ' is now ' . $orderStatus->name . '. ' . ($siteSetting->name ?? config('app.name')),
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $smsGateway->url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (\Throwable $e) {
            Log::error('Steadfast Webhook SMS Error', ['error' => $e->getMessage()]);
        }
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
}
