<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Customer;
use App\Models\District;
use App\Models\OrderStatus;
use App\Models\Order;
use App\Models\OrderAdminNote;
use App\Models\OrderHistory;
use App\Models\OrderDetails;
use App\Models\Shipping;
use App\Models\ShippingCharge;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Courierapi;
use App\Models\SmsGateway;
use App\Models\GeneralSetting;
use App\Models\Color;
use App\Models\Size;
use App\Models\ProductVariantPrice;
use App\Models\Coupon;
use Carbon\Carbon;
use App\Models\FundTransaction;
use App\Models\Vendor;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use App\Helpers\FundHelper;
use App\Models\Expense;
use App\Services\FacebookCapiService;
use App\Services\RedXService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Gloudemans\Shoppingcart\Facades\Cart;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    private const PRINTED_MARKER = '[PRINTED]';
    private const ADMIN_NOTE_PRESETS = [
        'আবার কল দিতে হবে',
        'কনফার্ম',
        'পরে জানাবেন',
        'কাস্টমার টাকা দিয়ে জানাবে',
        '1st Not Receive',
        'Number Busy',
        'Phone Off !!',
        'Double Order',
        'Unreachable',
        '2nd Not RCV',
        '3rd Not RCV',
        'Fraud Customer',
        'Cncl By Cstmr',
    ];

    /*
    |--------------------------------------------------------------------------
    | COMMON STOCK HANDLER
    |--------------------------------------------------------------------------
    |
    | activeStatuses = 1,2,3,5,6,8  => স্টক মাইনাস
    | newStatus = 11 এবং oldStatus active হলে => স্টক প্লাস
    |
    */
    protected function handleStockChange(Order $order, int $oldStatus, int $newStatus)
    {
        $activeStatuses = [1, 2, 3, 5, 6, 8];

        // 1) প্রথমবার active status এ ঢুকলে স্টক কমবে
        if (in_array($newStatus, $activeStatuses) && !in_array($oldStatus, $activeStatuses)) {
            $details = OrderDetails::where('order_id', $order->id)
                ->with('product:id,stock') // ✅ Eager load products to avoid N+1
                ->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = max(0, $row->product->stock - $row->qty);
                    $row->product->save();
                }
            }
        }

        // 2) cancel (11) হলে, যদি আগেরটা active group এ থাকে -> স্টক রিস্টোর
        if ($newStatus == 11 && in_array($oldStatus, $activeStatuses)) {
            $details = OrderDetails::where('order_id', $order->id)
                ->with('product:id,stock') // ✅ Eager load products to avoid N+1
                ->get();

            foreach ($details as $row) {
                if ($row->product) {
                    $row->product->stock = $row->product->stock + $row->qty;
                    $row->product->save();
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FRAUD CHECK PART
    |--------------------------------------------------------------------------
    */

    public function fraudCheck(Request $request)
    {
        $mobile = trim((string) $request->input('mobile'));

        if (!$mobile) {
            return response()->json(['status' => 'failed', 'message' => 'Mobile number missing']);
        }

        $cachedFraud = $this->getStoredFraudSummaryForPhone($mobile);
        if ($cachedFraud) {
            return response()->json([
                'status' => 'success',
                'source' => 'cache',
                'data' => [
                    'data' => $cachedFraud,
                ],
            ]);
        }

        $generalSetting = GeneralSetting::where('status', 1)->first();
        $apiKey = isset($generalSetting->fraud_api_key) ? $generalSetting->fraud_api_key : null;

        if ($apiKey) {
            try {
                $apiResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post('https://api.bdcourier.com/courier-check', [
                    'phone' => $mobile,
                ]);

                $apiPayload = $apiResponse->json();

                if (is_array($apiPayload) && (($apiPayload['status'] ?? null) === 'success') && !empty($apiPayload['data'])) {
                    $this->storeFraudSummaryForPhone($mobile, $apiPayload['data']);
                    return response()->json([
                        'status' => 'success',
                        'source' => 'fraud_api',
                        'data' => [
                            'data' => $apiPayload['data'],
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('External fraud API failed, falling back to courier summary', [
                    'mobile' => $mobile,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            $variants = $this->generateBangladeshPhoneVariants($mobile);
            $orders = Order::query()
                ->with('shipping:id,order_id,phone')
                ->whereHas('shipping', function ($q) use ($variants) {
                    $q->whereIn('phone', $variants);
                })
                ->where(function ($q) {
                    $q->whereNotNull('courier_type')
                        ->orWhereNotNull('courier_tracking_id')
                        ->orWhereNotNull('consignment_id');
                })
                ->orderByDesc('id')
                ->get();

            $courierConfigs = Courierapi::query()
                ->where('status', 1)
                ->whereIn('type', ['pathao', 'steadfast', 'redx'])
                ->get()
                ->keyBy('type');

            $pathaoDirect = $this->fetchPathaoPhoneSuccessRate($mobile, $courierConfigs->get('pathao'));
            if ($orders->isEmpty() && (($pathaoDirect['status'] ?? null) !== 'success')) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $pathaoDirect['message'] ?? 'No courier orders found for this mobile',
                ]);
            }

            $courierStats = [
                'pathao' => ['total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0, 'success_ratio' => 0],
                'steadfast' => ['total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0, 'success_ratio' => 0],
                'redx' => ['total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0, 'success_ratio' => 0],
                'paperfly' => ['total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0, 'success_ratio' => 0],
                'parceldex' => ['total_parcel' => 0, 'success_parcel' => 0, 'cancelled_parcel' => 0, 'success_ratio' => 0],
            ];
            if (($pathaoDirect['status'] ?? null) === 'success' && !empty($pathaoDirect['data'])) {
                $courierStats['pathao'] = [
                    'total_parcel' => (int) ($pathaoDirect['data']['total_parcel'] ?? 0),
                    'success_parcel' => (int) ($pathaoDirect['data']['success_parcel'] ?? 0),
                    'cancelled_parcel' => (int) ($pathaoDirect['data']['cancelled_parcel'] ?? 0),
                    'success_ratio' => (int) ($pathaoDirect['data']['success_ratio'] ?? 0),
                ];
            }

            $seen = [];

            foreach ($orders as $order) {
                $courierKey = strtolower(trim((string) ($order->courier_type ?: '')));
                if (!isset($courierStats[$courierKey])) {
                    continue;
                }

                if ($courierKey === 'pathao' && (($pathaoDirect['status'] ?? null) === 'success')) {
                    continue;
                }

                $identity = $courierKey . '|' . ($order->courier_tracking_id ?: $order->consignment_id ?: $order->invoice_id);
                if (isset($seen[$identity])) {
                    continue;
                }
                $seen[$identity] = true;

                $status = $this->fetchCourierStatusFromMerchantApi($order, $courierKey, $courierConfigs);
                if (!$status) {
                    continue;
                }

                $courierStats[$courierKey]['total_parcel']++;
                $bucket = $this->mapCourierStatusBucket($status);

                if ($bucket === 'success') {
                    $courierStats[$courierKey]['success_parcel']++;
                } elseif ($bucket === 'cancel') {
                    $courierStats[$courierKey]['cancelled_parcel']++;
                }
            }

            foreach ($courierStats as $key => $stats) {
                $courierStats[$key]['success_ratio'] = $stats['total_parcel'] > 0
                    ? round(($stats['success_parcel'] / $stats['total_parcel']) * 100)
                    : 0;
            }

            $summary = [
                'total_parcel' => 0,
                'success_parcel' => 0,
                'cancelled_parcel' => 0,
                'success_ratio' => 0,
            ];

            foreach (['pathao', 'steadfast', 'redx', 'paperfly', 'parceldex'] as $key) {
                $summary['total_parcel'] += $courierStats[$key]['total_parcel'];
                $summary['success_parcel'] += $courierStats[$key]['success_parcel'];
                $summary['cancelled_parcel'] += $courierStats[$key]['cancelled_parcel'];
            }

            $summary['success_ratio'] = $summary['total_parcel'] > 0
                ? round(($summary['success_parcel'] / $summary['total_parcel']) * 100)
                : 0;

            foreach ($orders as $order) {
                $order->pathao_success = $courierStats['pathao']['success_parcel'];
                $order->pathao_cancel = $courierStats['pathao']['cancelled_parcel'];
                $order->pathao_rate = $courierStats['pathao']['success_ratio'];
                $order->redx_success = $courierStats['redx']['success_parcel'];
                $order->redx_cancel = $courierStats['redx']['cancelled_parcel'];
                $order->redx_rate = $courierStats['redx']['success_ratio'];
                $order->steadfast_success = $courierStats['steadfast']['success_parcel'];
                $order->steadfast_cancel = $courierStats['steadfast']['cancelled_parcel'];
                $order->steadfast_rate = $courierStats['steadfast']['success_ratio'];
                $order->fraud_success = $summary['success_parcel'];
                $order->fraud_cancel = $summary['cancelled_parcel'];
                $order->fraud_rate = $summary['success_ratio'];
                $order->save();
            }

            if ($summary['total_parcel'] === 0) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $pathaoDirect['message'] ?? 'Configured courier APIs did not return any trackable parcel status for this mobile',
                ]);
            }

            $this->storeFraudSummaryForPhone($mobile, [
                'summary' => $summary,
                'pathao' => $courierStats['pathao'],
                'steadfast' => $courierStats['steadfast'],
                'redx' => $courierStats['redx'],
                'paperfly' => $courierStats['paperfly'],
                'parceldex' => $courierStats['parceldex'],
                'pathao_direct' => $pathaoDirect,
            ]);

            return response()->json([
                'status' => 'success',
                'source' => 'courier_summary',
                'data' => [
                    'data' => [
                        'summary' => $summary,
                        'pathao' => $courierStats['pathao'],
                        'steadfast' => $courierStats['steadfast'],
                        'redx' => $courierStats['redx'],
                        'paperfly' => $courierStats['paperfly'],
                        'parceldex' => $courierStats['parceldex'],
                        'pathao_direct' => $pathaoDirect,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Courier summary error: ' . $e->getMessage(),
            ]);
        }
    }

    private function normalizeBangladeshPhoneForFraud(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            return substr($digits, 0, 13);
        }

        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return '88' . $digits;
        }

        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            return '880' . $digits;
        }

        return $digits;
    }

    private function fraudCacheKey(string $phone): string
    {
        return 'fraud_summary_phone_' . $this->normalizeBangladeshPhoneForFraud($phone);
    }

    private function summarizeFraudPayload(array $raw): array
    {
        $getStats = function (array $obj): array {
            $total = (int) ($obj['total_parcel'] ?? $obj['total'] ?? $obj['orders'] ?? $obj['count'] ?? 0);
            $success = (int) ($obj['success_parcel'] ?? $obj['success'] ?? $obj['complete'] ?? $obj['delivered'] ?? 0);
            $cancel = (int) ($obj['cancelled_parcel'] ?? $obj['cancel'] ?? $obj['cancelled'] ?? $obj['failed'] ?? 0);
            $rate = isset($obj['success_ratio'])
                ? (int) $obj['success_ratio']
                : ($total > 0 ? (int) round(($success / $total) * 100) : 0);

            return [
                'total' => $total,
                'success' => $success,
                'cancel' => $cancel,
                'rate' => $rate,
            ];
        };

        $summary = is_array($raw['summary'] ?? null) ? $raw['summary'] : [];
        $pathao = $getStats((array) ($raw['pathao'] ?? $raw['Pathao'] ?? $raw['pathao_data'] ?? []));
        $redx = $getStats((array) ($raw['redx'] ?? $raw['RedX'] ?? $raw['redx_data'] ?? []));
        $steadfast = $getStats((array) ($raw['steadfast'] ?? $raw['Steadfast'] ?? $raw['steadfast_data'] ?? []));
        $paperfly = $getStats((array) ($raw['paperfly'] ?? $raw['PaperFly'] ?? []));
        $parceldex = $getStats((array) ($raw['parceldex'] ?? $raw['ParcelDex'] ?? []));
        $carrybee = $getStats((array) ($raw['carrybee'] ?? $raw['CarryBee'] ?? []));

        $total = (int) ($summary['total_parcel'] ?? ($pathao['total'] + $redx['total'] + $steadfast['total'] + $paperfly['total'] + $parceldex['total'] + $carrybee['total']));
        $success = (int) ($summary['success_parcel'] ?? ($pathao['success'] + $redx['success'] + $steadfast['success'] + $paperfly['success'] + $parceldex['success'] + $carrybee['success']));
        $cancel = (int) ($summary['cancelled_parcel'] ?? ($pathao['cancel'] + $redx['cancel'] + $steadfast['cancel'] + $paperfly['cancel'] + $parceldex['cancel'] + $carrybee['cancel']));
        $rate = isset($summary['success_ratio'])
            ? (int) $summary['success_ratio']
            : ($total > 0 ? (int) round(($success / $total) * 100) : 0);

        return [
            'total' => $total,
            'success' => $success,
            'cancel' => $cancel,
            'rate' => $rate,
        ];
    }

    private function storeFraudSummaryForPhone(string $mobile, array $payload): void
    {
        $cacheKey = $this->fraudCacheKey($mobile);
        if ($cacheKey === 'fraud_summary_phone_') {
            return;
        }

        Cache::put($cacheKey, $payload, now()->addDays(7));

        $summary = $this->summarizeFraudPayload($payload);
        $variants = $this->generateBangladeshPhoneVariants($mobile);

        if (empty($variants)) {
            return;
        }

        $orders = Order::query()
            ->with('shipping:id,order_id,phone')
            ->whereHas('shipping', function ($query) use ($variants) {
                $query->whereIn('phone', $variants);
            })
            ->get();

        foreach ($orders as $order) {
            $order->fraud_success = $summary['success'];
            $order->fraud_cancel = $summary['cancel'];
            $order->fraud_rate = $summary['rate'];
            $order->save();
        }
    }

    private function getStoredFraudSummaryForPhone(string $mobile): ?array
    {
        $cacheKey = $this->fraudCacheKey($mobile);
        if ($cacheKey === 'fraud_summary_phone_') {
            return null;
        }

        $payload = Cache::get($cacheKey);

        return is_array($payload) && !empty($payload) ? $payload : null;
    }

    public function manualFraudCheckPage()
    {
        return view('backEnd.fraud.manual_check');
    }

    private function generateBangladeshPhoneVariants(string $phone): array
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw);
        $variants = [];

        if ($raw !== '') {
            $variants[] = $raw;
        }

        if ($digits !== '') {
            $variants[] = $digits;
        }

        if (str_starts_with($digits, '880') && strlen($digits) >= 13) {
            $local = '0' . substr($digits, 3);
            $variants[] = $local;
            $variants[] = '+' . $digits;
        } elseif (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $intl = '88' . $digits;
            $variants[] = $intl;
            $variants[] = '+' . $intl;
        } elseif (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $local = '0' . $digits;
            $intl = '88' . $local;
            $variants[] = $local;
            $variants[] = $intl;
            $variants[] = '+' . $intl;
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function fetchCourierStatusFromMerchantApi(Order $order, string $courierKey, $courierConfigs): ?string
    {
        try {
            if ($courierKey === 'pathao') {
                $config = $courierConfigs->get('pathao');
                if (!$config || empty($config->token)) {
                    return null;
                }

                $trackingId = $order->courier_tracking_id ?: $order->consignment_id;
                if (!$trackingId) {
                    return null;
                }

                $baseUrl = rtrim($config->url, '/');
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $config->token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->timeout(20)->get($baseUrl . '/aladdin/api/v1/orders/' . $trackingId . '/info');

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();
                return strtolower((string) ($data['data']['order_status_slug'] ?? $data['data']['order_status'] ?? ''));
            }

            if ($courierKey === 'steadfast') {
                $config = $courierConfigs->get('steadfast');
                if (!$config || empty($config->api_key) || empty($config->secret_key)) {
                    return null;
                }

                $invoice = $order->invoice_id;
                if (!$invoice) {
                    return null;
                }

                $baseUrl = preg_replace('#/create_order/?$#', '', rtrim((string) $config->url, '/'));
                $response = Http::withHeaders([
                    'Api-Key' => $config->api_key,
                    'Secret-Key' => $config->secret_key,
                    'Accept' => 'application/json',
                ])->timeout(20)->get($baseUrl . '/status_by_invoice/' . $invoice);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();
                return strtolower((string) ($data['delivery_status'] ?? $data['status'] ?? ''));
            }

            if ($courierKey === 'redx') {
                $trackingId = $order->courier_tracking_id ?: $order->consignment_id;
                if (!$trackingId) {
                    return null;
                }

                $service = new RedXService();
                $details = $service->getParcelDetails((string) $trackingId);
                if (is_array($details)) {
                    $status = $details['status'] ?? $details['data']['status'] ?? $details['parcel']['status'] ?? $details['data']['parcel_status'] ?? null;
                    if ($status) {
                        return strtolower((string) $status);
                    }
                }

                $track = $service->trackParcel((string) $trackingId);
                if (is_array($track)) {
                    $status = $track['status'] ?? $track['data']['status'] ?? $track['parcel']['status'] ?? $track['data']['parcel_status'] ?? null;
                    if ($status) {
                        return strtolower((string) $status);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Courier status fetch failed', [
                'courier' => $courierKey,
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function fetchPathaoPhoneSuccessRate(string $mobile, $pathaoConfig): array
    {
        if (!$pathaoConfig || empty($pathaoConfig->token)) {
            return [
                'status' => 'failed',
                'message' => 'Pathao token is missing',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pathaoConfig->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://merchant.pathao.com/api/v1/user/success', [
                'phone' => $mobile,
            ]);

            $decoded = $response->json();
            if (!is_array($decoded)) {
                return [
                    'status' => 'failed',
                    'message' => 'Invalid Pathao response',
                ];
            }

            $payload = $decoded['data']['data'] ?? $decoded['data'] ?? [];
            $message = $decoded['data']['message'] ?? $decoded['message'] ?? null;
            $code = $decoded['data']['code'] ?? $decoded['code'] ?? $response->status();
            $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
            $processed = (int) (
                $customer['total_delivery']
                ?? $payload['processed']
                ?? $payload['total_parcel']
                ?? $payload['total']
                ?? 0
            );
            $delivered = (int) (
                $customer['successful_delivery']
                ?? $payload['delivered']
                ?? $payload['success_parcel']
                ?? 0
            );
            $returned = (int) (
                $customer['returned_delivery']
                ?? $customer['failed_delivery']
                ?? $payload['returned']
                ?? $payload['cancelled_parcel']
                ?? max($processed - $delivered, 0)
            );

            return [
                'status' => ($response->successful() && in_array((int) $code, [200, 206], true)) ? 'success' : 'failed',
                'message' => $message,
                'data' => [
                    'total_parcel' => $processed,
                    'success_parcel' => $delivered,
                    'cancelled_parcel' => $returned,
                    'success_ratio' => $processed > 0 ? (int) round(($delivered / $processed) * 100) : 0,
                    'raw' => $payload,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Pathao success-rate request failed: ' . $e->getMessage(),
            ];
        }
    }

    private function mapCourierStatusBucket(string $status): ?string
    {
        $normalized = strtolower(trim(str_replace(['-', ' '], '_', $status)));

        $successKeywords = ['delivered', 'completed', 'complete', 'delivery_done'];
        $cancelKeywords = ['cancel', 'cancelled', 'pickup_cancel', 'pickup_cancelled', 'returned', 'return', 'partial_delivered', 'partial_delivery', 'return_to_merchant', 'delivery_failed'];

        foreach ($successKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'success';
            }
        }

        foreach ($cancelKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'cancel';
            }
        }

        return null;
    }

    private function resolveOrderStatusName(?int $statusId): string
    {
        if (!$statusId) {
            return 'N/A';
        }

        return (string) (OrderStatus::where('id', $statusId)->value('name') ?? ('Status #' . $statusId));
    }

    private function maybeSendFacebookCapiPurchaseForStatus(Order $order, int $statusId): void
    {
        $statusName = $this->resolveOrderStatusName($statusId);

        try {
            app(FacebookCapiService::class)->sendPurchaseForOrderStatus(
                $order,
                $statusName,
                $order->customer_payable_amount ?? $order->amount,
                request()->fullUrl()
            );
        } catch (\Throwable $e) {
            Log::error('Facebook CAPI Purchase status trigger failed for order ' . $order->id . ': ' . $e->getMessage());
        }
    }

    private function buildOrderHistoryChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $label => $newValue) {
            $oldValue = $before[$label] ?? null;
            $oldNormalized = is_scalar($oldValue) || $oldValue === null ? trim((string) $oldValue) : json_encode($oldValue, JSON_UNESCAPED_UNICODE);
            $newNormalized = is_scalar($newValue) || $newValue === null ? trim((string) $newValue) : json_encode($newValue, JSON_UNESCAPED_UNICODE);

            if ($oldNormalized === $newNormalized) {
                continue;
            }

            $changes[$label] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    private function buildOrderHistorySnapshot(Order $order, ?Shipping $shipping = null, ?string $itemsSummary = null): array
    {
        $shipping = $shipping ?: $order->shipping;
        $statusName = $this->resolveOrderStatusName((int) ($order->order_status ?? 0));
        $source = trim((string) ($order->note ?? ''));
        $courierNote = trim((string) ($order->order_note ?? ''));

        if ($source === '') {
            $source = 'Website';
        }

        if ($itemsSummary === null) {
            $detailQuery = OrderDetails::where('order_id', $order->id);
            $itemCount = (int) $detailQuery->count();
            $qtyCount = (int) $detailQuery->sum('qty');
            $itemsSummary = $itemCount . ' item(s), Qty ' . $qtyCount;
        }

        return [
            'Status' => $statusName,
            'Customer Name' => trim((string) ($shipping->name ?? '')),
            'Phone' => trim((string) ($shipping->phone ?? '')),
            'Address' => trim((string) ($shipping->address ?? '')),
            'Area' => trim((string) ($shipping->area ?? '')),
            'Order Source' => $source,
            'Courier Note' => $courierNote,
            'Amount' => number_format((float) ($order->amount ?? 0), 2, '.', ''),
            'Shipping Charge' => number_format((float) ($order->shipping_charge ?? 0), 2, '.', ''),
            'Discount' => number_format((float) ($order->discount ?? 0), 2, '.', ''),
            'Payment Status' => trim((string) ($order->payment_status ?? '')),
            'Items' => $itemsSummary,
        ];
    }

    private function buildCartItemsHistorySummary(): string
    {
        $cartItems = Cart::instance('pos_shopping')->content();

        return $cartItems->count() . ' item(s), Qty ' . (int) $cartItems->sum('qty');
    }

    private function resolveOrderHistoryActor(Order $order, ?int $changedBy = null, ?string $actorName = null, ?string $actorType = null): array
    {
        if ($actorName) {
            return [
                'changed_by' => $changedBy,
                'actor_name' => $actorName,
                'actor_type' => $actorType ?: 'system',
            ];
        }

        $resolvedUserId = $changedBy ?: (Auth::guard('admin')->id() ?: auth()->id());

        if ($resolvedUserId) {
            $user = User::select('id', 'name')->find($resolvedUserId);
            if ($user) {
                return [
                    'changed_by' => $user->id,
                    'actor_name' => $user->name,
                    'actor_type' => $actorType ?: 'admin',
                ];
            }
        }

        if (!empty($order->user_id) && (float) ($order->reseller_profit ?? 0) > 0) {
            $reseller = $order->relationLoaded('user') ? $order->user : User::select('id', 'name')->find($order->user_id);
            if ($reseller) {
                return [
                    'changed_by' => $reseller->id,
                    'actor_name' => $reseller->name,
                    'actor_type' => 'reseller',
                ];
            }
        }

        $customerName = trim((string) optional($order->shipping)->name);

        return [
            'changed_by' => null,
            'actor_name' => $customerName !== '' ? $customerName : 'Customer',
            'actor_type' => $actorType ?: 'customer',
        ];
    }

    private function recordOrderHistory(Order $order, string $eventType, string $title, ?string $description = null, array $changes = [], ?int $statusId = null, ?int $changedBy = null, ?string $actorName = null, ?string $actorType = null, $createdAt = null): void
    {
        if (!Schema::hasTable('order_histories')) {
            return;
        }

        $actor = $this->resolveOrderHistoryActor($order, $changedBy, $actorName, $actorType);
        $resolvedStatusId = $statusId ?: (int) ($order->order_status ?? 0);
        $statusName = $this->resolveOrderStatusName($resolvedStatusId);

        OrderHistory::create([
            'order_id' => $order->id,
            'status_id' => $resolvedStatusId ?: null,
            'changed_by' => $actor['changed_by'],
            'event_type' => $eventType,
            'title' => $title,
            'status_name' => $statusName !== 'N/A' ? $statusName : null,
            'actor_name' => $actor['actor_name'],
            'actor_type' => $actor['actor_type'],
            'description' => $description,
            'changes' => !empty($changes) ? $changes : null,
            'created_at' => $createdAt ?: now(),
            'updated_at' => $createdAt ?: now(),
        ]);
    }

    private function getAdminNotePresetOptions(): array
    {
        return self::ADMIN_NOTE_PRESETS;
    }

    private function sanitizeAdminNoteText(?string $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        $text = str_replace(self::PRINTED_MARKER, '', $text);
        $text = preg_replace("/(\r?\n)+/", PHP_EOL, $text);

        return trim((string) $text);
    }

    private function preservePrintedMarker(?string $existingNote, string $newNote): string
    {
        $hasPrintedMarker = str_contains((string) $existingNote, self::PRINTED_MARKER);
        $cleanNote = trim($newNote);

        if ($hasPrintedMarker) {
            return $cleanNote === ''
                ? self::PRINTED_MARKER
                : $cleanNote . PHP_EOL . self::PRINTED_MARKER;
        }

        return $cleanNote;
    }

    public function manualFraudCheck(Request $request)
    {
        $mobile = $request->input('mobile');

        if (!$mobile) {
            return back()->with('error', 'দয়া করে একটি মোবাইল নাম্বার লিখুন');
        }

        // 1. ডাটাবেস থেকে সেটিংস আনা
        $generalSetting = GeneralSetting::where('status', 1)->first();
        $apiKey = isset($generalSetting->fraud_api_key) ? $generalSetting->fraud_api_key : null;

        if (!$apiKey) {
            return back()->with('error', 'Fraud API Key সেটিংস প্যানেলে সেট করা নেই');
        }

        $apiUrl = 'https://api.bdcourier.com/courier-check';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($apiUrl, [
                'phone' => $mobile,
            ]);

            $res = $response->json();

            if (isset($res['status']) && $res['status'] === 'success') {
                $data = $res['data'] ?? [];

                return view('backEnd.fraud.manual_check', compact('mobile', 'data'));

            } else {
                return back()->with('error', isset($res['message']) ? $res['message'] : 'Fraud check ব্যর্থ হয়েছে');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'API Error: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE ORDER CHECK PART
    |--------------------------------------------------------------------------
    */

    public function duplicateOrderCheck(Request $request)
    {
        $mobile = $request->input('mobile');

        if (!$mobile) {
            return response()->json(['status' => 'failed', 'message' => 'Mobile number missing']);
        }

        // সেটিংস থেকে Duplicate Order API Key নেওয়া
        $generalSetting = GeneralSetting::where('status', 1)->first();
        $apiKey = isset($generalSetting->duplicate_order_api_key) ? $generalSetting->duplicate_order_api_key : null;

        if (!$apiKey) {
            return response()->json(['status' => 'failed', 'message' => 'Duplicate Order API Key missing']);
        }

        try {
            // API কল করা (Duplicate Order API)
            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("https://www.creativedesign.com.bd/api/v1/check-duplicate-order", [
                'phone' => $mobile,
            ]);

            $res = $response->json();

            if (isset($res['status']) && $res['status'] === 'success') {
                
                // এই মোবাইল নাম্বারের সব অর্ডার খুঁজে বের করা
                $orders = Order::whereHas('shipping', function ($q) use ($mobile) {
                    $q->where('phone', $mobile);
                })->get();

                if ($orders->isEmpty()) {
                    return response()->json(['status' => 'failed', 'message' => 'Order not found for this mobile']);
                }

                // সব অর্ডারে লুপ চালিয়ে ডাটা আপডেট করা
                foreach ($orders as $order) {
                    
                    if (isset($res['is_duplicate']) && $res['is_duplicate'] === true) {
                        $order->is_duplicate_order = 1; 
                        $order->duplicate_order_count = isset($res['duplicate_count']) ? $res['duplicate_count'] : 0;
                        $order->duplicate_order_rate = isset($res['duplicate_rate']) ? $res['duplicate_rate'] : 0;
                        $order->last_duplicate_order_date = isset($res['last_duplicate_date']) ? \Carbon\Carbon::parse($res['last_duplicate_date']) : null;
                    } 
                    elseif (isset($res['data'])) {
                        $cData = $res['data'];

                        // Duplicate order related data
                        $order->is_duplicate_order = isset($cData['is_duplicate']) && $cData['is_duplicate'] === true ? 1 : 0;
                        $order->duplicate_order_count = isset($cData['duplicate_count']) ? $cData['duplicate_count'] : 0;
                        $order->duplicate_order_rate = isset($cData['duplicate_rate']) ? $cData['duplicate_rate'] : 0;
                        $order->last_duplicate_order_date = isset($cData['last_duplicate_date']) ? \Carbon\Carbon::parse($cData['last_duplicate_date']) : null;
                    }
                    $order->save();
                }

                return response()->json([
                    'status' => 'success',
                    'data'   => $res
                ]);
            } else {
                return response()->json(['status' => 'failed', 'message' => 'API Error']);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function manualDuplicateOrderCheckPage()
    {
        return view('backEnd.duplicate_order.manual_check');
    }

    public function manualDuplicateOrderCheck(Request $request)
    {
        $mobile = $request->input('mobile');

        if (!$mobile) {
            return back()->with('error', 'দয়া করে একটি মোবাইল নাম্বার লিখুন');
        }

        // 1. ডাটাবেস থেকে সেটিংস আনা
        $generalSetting = GeneralSetting::where('status', 1)->first();
        $apiKey = isset($generalSetting->duplicate_order_api_key) ? $generalSetting->duplicate_order_api_key : null;

        if (!$apiKey) {
            return back()->with('error', 'Duplicate Order API Key সেটিংস প্যানেলে সেট করা নেই');
        }

        $apiUrl = "https://www.creativedesign.com.bd/api/v1/check-duplicate-order";

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json'
            ])->post($apiUrl, [
                'phone' => $mobile,
            ]);

            $res = $response->json();

            if (isset($res['status']) && $res['status'] === 'success') {
                
                if (isset($res['is_duplicate']) && $res['is_duplicate'] === true) {
                    $data = [
                        'is_duplicate' => true,
                        'message'  => isset($res['message']) ? $res['message'] : 'Duplicate order detected',
                        'duplicate_count' => isset($res['duplicate_count']) ? $res['duplicate_count'] : 0
                    ];
                } else {
                    $data = isset($res['data']) ? $res['data'] : [];
                }
                
                return view('backEnd.duplicate_order.manual_check', compact('mobile', 'data'));

            } else {
                return back()->with('error', isset($res['message']) ? $res['message'] : 'Duplicate order check ব্যর্থ হয়েছে');
            }
        } catch (\Exception $e) {
            return back()->with('error', 'API Error: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER LIST
    |--------------------------------------------------------------------------
    */

    private function applyOrderKeywordFilter($query, Request $request): void
    {
        $keyword = trim((string) $request->keyword);

        if ($keyword === '') {
            return;
        }

        $query->where(function ($builder) use ($keyword) {
            $builder->where('invoice_id', 'LIKE', '%' . $keyword . '%')
                ->orWhereHas('shipping', function ($shippingQuery) use ($keyword) {
                    $shippingQuery->where('phone', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('name', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('address', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('area', 'LIKE', '%' . $keyword . '%');
                });
        });
    }

    private function applyOrderAdvancedFilters($query, Request $request, bool $supportsOrderCreatedBy): void
    {
        $dateFrom = trim((string) $request->filter_date_from);
        $dateTo = trim((string) $request->filter_date_to);
        $courier = strtolower(trim((string) $request->filter_courier));
        $district = trim((string) $request->filter_district);
        $orderSource = trim((string) $request->filter_order_source);
        $creator = trim((string) $request->filter_creator);

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($courier !== '') {
            if ($courier === 'none') {
                $query->whereNull('courier_type')
                    ->whereNull('courier_tracking_id')
                    ->whereNull('consignment_id');
            } elseif ($courier === 'steadfast') {
                $query->where(function ($courierQuery) {
                    $courierQuery->where('courier_type', 'steadfast')
                        ->orWhere(function ($fallbackQuery) {
                            $fallbackQuery->whereNull('courier_type')
                                ->whereNotNull('consignment_id');
                        });
                });
            } else {
                $query->where('courier_type', $courier);
            }
        }

        if ($district !== '') {
            $query->whereHas('shipping', function ($shippingQuery) use ($district) {
                $shippingQuery->where('address', 'LIKE', '%' . $district . '%')
                    ->orWhere('area', 'LIKE', '%' . $district . '%');
            });
        }

        if ($orderSource !== '') {
            if (in_array(strtolower($orderSource), ['web site', 'website'], true)) {
                $query->where(function ($orderSourceQuery) use ($supportsOrderCreatedBy) {
                    $orderSourceQuery->where(function ($websiteQuery) use ($supportsOrderCreatedBy) {
                        $websiteQuery->where(function ($noteQuery) {
                            $noteQuery->whereNull('note')
                                ->orWhere('note', '');
                        });

                        if ($supportsOrderCreatedBy) {
                            $websiteQuery->whereNull('created_by');
                        }

                        $websiteQuery->where(function ($resellerQuery) {
                            $resellerQuery->whereNull('reseller_profit')
                                ->orWhere('reseller_profit', '<=', 0);
                        });
                    })->orWhere('note', 'Website')
                        ->orWhere('note', 'Web Site')
                        ->orWhere('note', 'LIKE', 'Order Source: Website%')
                        ->orWhere('note', 'LIKE', 'Order Source: Web Site%');
                });
            } else {
                $query->where(function ($orderSourceQuery) use ($orderSource) {
                    $orderSourceQuery->where('note', $orderSource)
                        ->orWhere('note', 'LIKE', 'Order Source: ' . $orderSource . '%');
                });
            }
        }

        if ($creator !== '') {
            if ($creator === 'customer') {
                if ($supportsOrderCreatedBy) {
                    $query->whereNull('created_by');
                }

                $query->where(function ($creatorQuery) {
                    $creatorQuery->whereNull('reseller_profit')
                        ->orWhere('reseller_profit', '<=', 0);
                });
            } elseif ($creator === 'reseller') {
                $query->where('reseller_profit', '>', 0);
            } elseif ($supportsOrderCreatedBy && str_starts_with($creator, 'admin:')) {
                $creatorId = (int) substr($creator, 6);

                if ($creatorId > 0) {
                    $query->where('created_by', $creatorId);
                }
            }
        }
    }

    public function index($slug, Request $request)
    {
        $supportsOrderCreatedBy = Schema::hasColumn('orders', 'created_by');
        $baseOrderQuery = Order::query();
        $allowedPerPage = [10, 25, 50, 100, 300, 500, 700, 1000];
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $this->applyOrderKeywordFilter($baseOrderQuery, $request);
        $this->applyOrderAdvancedFilters($baseOrderQuery, $request, $supportsOrderCreatedBy);

        if ($slug == 'all') {
            // ✅ Cache order count for 5 minutes
            $orders_count = (clone $baseOrderQuery)->count();
            
            $order_status = (object) [
                'name'         => 'All',
                'orders_count' => $orders_count,
            ];

            $show_data = (clone $baseOrderQuery)
                ->latest()
                ->with([
                    'shipping:id,order_id,name,phone,address',
                    'status:id,name,slug',
                    'customer:id,name,phone,email',
                    'user:id,name,email,shop_name',
                    'createdByAdmin:id,name,email',
                    'orderdetails:id,order_id,product_id,vendor_id,product_name,qty,sale_price',
                    'orderdetails.vendor:id,shop_name,owner_name'
                ]);

            $show_data = $show_data->paginate($perPage);
        } elseif ($slug == 'normal-customers') {
            $inCourierStatusId = $this->resolveCourierInStatusId();
            $normalCustomerBaseQuery = (clone $baseOrderQuery)->where(function ($query) {
                    $query->whereNull('reseller_profit')
                        ->orWhere('reseller_profit', '<=', 0);
                })
                ->whereNull('courier_type')
                ->whereNull('courier_tracking_id')
                ->whereNull('consignment_id');

            if ($inCourierStatusId) {
                $normalCustomerBaseQuery->where('order_status', '!=', $inCourierStatusId);
            }

            $orders_count = (clone $normalCustomerBaseQuery)->count();

            $order_status = (object) [
                'name'         => 'Customer',
                'orders_count' => $orders_count,
            ];

            $show_data = $normalCustomerBaseQuery
                ->latest()
                ->with([
                    'shipping:id,order_id,name,phone,address',
                    'status:id,name,slug',
                    'customer:id,name,phone,email',
                    'user:id,name,email,shop_name',
                    'createdByAdmin:id,name,email',
                    'orderdetails:id,order_id,product_id,vendor_id,product_name,qty,sale_price',
                    'orderdetails.vendor:id,shop_name,owner_name'
                ]);

            $show_data = $show_data->paginate($perPage);
        } else {
            // ✅ Cache order status with count
            $order_status = OrderStatus::where('slug', $slug)->first();

            if (!$order_status) {
                abort(404);
            }
            
            $show_data = (clone $baseOrderQuery)
                ->where(['order_status' => $order_status->id])
                ->latest()
                ->with([
                    'shipping:id,order_id,name,phone,address',
                    'status:id,name,slug',
                    'customer:id,name,phone,email',
                    'user:id,name,email,shop_name',
                    'createdByAdmin:id,name,email',
                    'orderdetails:id,order_id,product_id,vendor_id,product_name,qty,sale_price',
                    'orderdetails.vendor:id,shop_name,owner_name'
                ])
                ->paginate($perPage);
        }

        // ✅ Cache users dropdown for 10 minutes
        $users = Cache::remember('users_dropdown', 600, function () {
            return User::select('id', 'name')->limit(100)->get();
        });
        
        // ✅ Cache courier APIs for 30 minutes
        $steadfast = Cache::remember('courier_steadfast', 1800, function () {
            return Courierapi::where(['status' => 1, 'type' => 'steadfast'])->first();
        });
        
        $pathao_info = Cache::remember('courier_pathao', 1800, function () {
            return Courierapi::where(['status' => 1, 'type' => 'pathao'])
                ->select('id', 'type', 'url', 'token', 'status')
                ->first();
        });

        // ✅ Cache Pathao API responses for 10 minutes (API calls are slow)
        if ($pathao_info && $pathao_info->token) {
            $pathaocities = Cache::remember('pathao_cities', 600, function () use ($pathao_info) {
                try {
                    $baseUrl = rtrim($pathao_info->url, '/');
                    $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);
                    
                    $response = Http::timeout(5)->withHeaders([
                        'Authorization' => 'Bearer ' . $pathao_info->token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json'
                    ])->get($baseUrl . '/aladdin/api/v1/city-list');
                    
                    return $response->json() ?? [];
                } catch (\Exception $e) {
                    \Log::error('Pathao cities fetch failed', ['error' => $e->getMessage()]);
                    return [];
                }
            });

            $pathaostore = Cache::remember('pathao_stores', 600, function () use ($pathao_info) {
                try {
                    $baseUrl = rtrim($pathao_info->url, '/');
                    $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);
                    
                    $response2 = Http::timeout(5)->withHeaders([
                        'Authorization' => 'Bearer ' . $pathao_info->token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json'
                    ])->get($baseUrl . '/aladdin/api/v1/stores');
                    
                    return $response2->json() ?? [];
                } catch (\Exception $e) {
                    \Log::error('Pathao stores fetch failed', ['error' => $e->getMessage()]);
                    return [];
                }
            });
        } else {
            $pathaocities = [];
            $pathaostore  = [];
        }

        // ✅ Cache RedX API responses for 10 minutes
        $redx_info = Cache::remember('courier_redx', 1800, function () {
            return Courierapi::where(['status' => 1, 'type' => 'redx'])->first();
        });
        $paperfly_info = Cache::remember('courier_paperfly', 1800, function () {
            return Courierapi::where(['status' => 1, 'type' => 'paperfly'])->first();
        });
        
        $redxAreas = [];
        $redxPickupStores = [];
        
        if ($redx_info && $redx_info->token) {
            $redxAreas = Cache::remember('redx_areas', 600, function () use ($redx_info) {
                try {
                    $redxService = new RedXService();
                    $areasResult = $redxService->getAreas();
                    return $areasResult && isset($areasResult['areas']) ? $areasResult['areas'] : [];
                } catch (\Exception $e) {
                    \Log::error('RedX areas fetch failed', ['error' => $e->getMessage()]);
                    return [];
                }
            });
            
            $redxPickupStores = Cache::remember('redx_pickup_stores', 600, function () use ($redx_info) {
                try {
                    $redxService = new RedXService();
                    $storesResult = $redxService->getPickupStores();
                    return $storesResult && isset($storesResult['pickup_stores']) ? $storesResult['pickup_stores'] : [];
                } catch (\Exception $e) {
                    \Log::error('RedX stores fetch failed', ['error' => $e->getMessage()]);
                    return [];
                }
            });
        }

        // ✅ Cache blocked IPs for 5 minutes
        $blockedIps = Cache::remember('blocked_ips', 300, function () {
            return \App\Models\IpBlock::pluck('ip_no')->toArray();
        });
        
        // ✅ Cache order statuses for 30 minutes
        $statusCounts = (clone $baseOrderQuery)
            ->selectRaw('order_status, COUNT(*) as aggregate')
            ->groupBy('order_status')
            ->pluck('aggregate', 'order_status');

        $orderstatus = OrderStatus::query()
            ->orderByRaw("
                CASE
                    WHEN LOWER(slug) = 'new' THEN 1
                    WHEN LOWER(slug) = 'pending' THEN 2
                    WHEN LOWER(slug) = 'processing' THEN 3
                    WHEN LOWER(slug) = 'wfp' THEN 4
                    WHEN LOWER(slug) IN ('on-the-way', 'on_the_way') THEN 5
                    WHEN LOWER(slug) IN ('in-courier', 'in_courier') THEN 6
                    WHEN LOWER(slug) = 'completed' THEN 7
                    WHEN LOWER(slug) = 'unpaid' THEN 8
                    WHEN LOWER(slug) = 'cancelled' THEN 9
                    WHEN LOWER(slug) = 'delivered' THEN 10
                    WHEN LOWER(slug) IN ('partial-delivered', 'partial_delivered') THEN 11
                    WHEN LOWER(slug) = 'returned' THEN 12
                    WHEN LOWER(slug) IN ('paid-return', 'paid_return') THEN 13
                    ELSE 999
                END
            ")
            ->orderBy('id')
            ->get()
            ->map(function ($status) use ($statusCounts) {
                $status->orders_count = (int) ($statusCounts[$status->id] ?? 0);
                return $status;
            });

        $allOrdersCount = (clone $baseOrderQuery)->count();

        $topStatusCards = collect([
            ['slug' => 'all', 'label' => 'Total Orders', 'icon' => 'fe-shopping-bag', 'tone' => 'primary', 'count' => $allOrdersCount],
            ['slug' => 'pending', 'label' => 'Pending', 'icon' => 'fe-clock', 'tone' => 'warning'],
            ['slug' => 'processing', 'label' => 'Approved', 'icon' => 'fe-package', 'tone' => 'info'],
            ['slug' => 'in-courier', 'label' => 'In Courier', 'icon' => 'fe-truck', 'tone' => 'accent'],
            ['slug' => 'delivered', 'label' => 'Delivered', 'icon' => 'fe-check-circle', 'tone' => 'success'],
            ['slug' => 'returned', 'label' => 'Returned', 'icon' => 'fe-corner-up-left', 'tone' => 'danger'],
            ['slug' => 'paid-return', 'label' => 'Paid Return', 'icon' => 'fe-dollar-sign', 'tone' => 'warning'],
        ])->map(function ($card) use ($orderstatus) {
            if (!isset($card['count'])) {
                $matched = $orderstatus->first(function ($status) use ($card) {
                    $slug = strtolower((string) ($status->slug ?? ''));
                    $name = strtolower((string) ($status->name ?? ''));
                    return $slug === $card['slug'] || $name === $card['slug'];
                });
                $card['count'] = (int) ($matched->orders_count ?? 0);
            }

            return $card;
        });

        $filterCourierOptions = Courierapi::query()
            ->where('status', 1)
            ->orderBy('type')
            ->pluck('type')
            ->map(function ($type) {
                return strtolower(trim((string) $type));
            })
            ->filter()
            ->unique()
            ->values();

        $districtOptions = District::query()
            ->whereNotNull('district')
            ->select('district')
            ->distinct()
            ->orderBy('district')
            ->pluck('district');

        $orderSourceFilterOptions = collect([
            'Web Site',
            'FB',
            'Whatsapp',
            'Landing Page',
            'Messenger',
            'Phone Call',
            'Reseller',
            'Imo',
        ]);

        $customerLifetimeBadges = [];
        $persistedFraudSummaries = [];
        $phones = collect($show_data->items())
            ->map(function ($order) {
                return trim((string) optional($order->shipping)->phone);
            })
            ->filter(function ($phone) {
                return $phone !== '' && $phone !== '-';
            })
            ->unique()
            ->values();

        foreach ($phones as $phone) {
            $storedFraud = $this->getStoredFraudSummaryForPhone($phone);
            if ($storedFraud) {
                $persistedFraudSummaries[$phone] = $this->summarizeFraudPayload($storedFraud);
            }
        }

        if ($phones->isNotEmpty()) {
            $deliveredStatusIds = $orderstatus->filter(function ($status) {
                $slug = strtolower((string) ($status->slug ?? ''));
                $name = strtolower((string) ($status->name ?? ''));
                return in_array($slug, ['delivered', 'completed', 'complete'], true)
                    || in_array($name, ['delivered', 'completed', 'complete'], true);
            })->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();

            $cancelReturnStatusIds = $orderstatus->filter(function ($status) {
                $slug = strtolower((string) ($status->slug ?? ''));
                $name = strtolower((string) ($status->name ?? ''));
                return in_array($slug, ['cancelled', 'canceled', 'cancel', 'returned', 'return'], true)
                    || in_array($name, ['cancelled', 'canceled', 'cancel', 'returned', 'return'], true);
            })->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();

            $historyOrderSelect = ['id', 'order_status', 'reseller_profit'];
            if ($supportsOrderCreatedBy) {
                $historyOrderSelect[] = 'created_by';
            }

            $historyOrders = Order::query()
                ->select($historyOrderSelect)
                ->with(['shipping:id,order_id,phone'])
                ->whereHas('shipping', function ($query) use ($phones) {
                    $query->whereIn('phone', $phones->all());
                })
                ->get();

            $historyByPhone = [];

            foreach ($historyOrders as $historyOrder) {
                $phone = trim((string) optional($historyOrder->shipping)->phone);
                if ($phone === '') {
                    continue;
                }

                $isWebsiteOrder = (!$supportsOrderCreatedBy || empty($historyOrder->created_by))
                    && (float) ($historyOrder->reseller_profit ?? 0) <= 0;

                if (!$isWebsiteOrder) {
                    continue;
                }

                if (!isset($historyByPhone[$phone])) {
                    $historyByPhone[$phone] = [
                        'total' => 0,
                        'delivered' => 0,
                        'cancel_return' => 0,
                    ];
                }

                $historyByPhone[$phone]['total']++;

                if (in_array((int) $historyOrder->order_status, $deliveredStatusIds, true)) {
                    $historyByPhone[$phone]['delivered']++;
                }

                if (in_array((int) $historyOrder->order_status, $cancelReturnStatusIds, true)) {
                    $historyByPhone[$phone]['cancel_return']++;
                }
            }

            foreach ($historyByPhone as $phone => $stats) {
                $total = (int) ($stats['total'] ?? 0);
                $delivered = (int) ($stats['delivered'] ?? 0);
                $cancelReturn = (int) ($stats['cancel_return'] ?? 0);

                if ($total <= 0) {
                    continue;
                }

                if ($delivered === 0 && $cancelReturn === $total) {
                    $customerLifetimeBadges[$phone] = [
                        'label' => 'Fraud-' . $total,
                        'class' => 'order-courier-count-fraud',
                    ];
                } elseif ($total > 3 && $delivered === $total) {
                    $customerLifetimeBadges[$phone] = [
                        'label' => 'VIP-' . $total,
                        'class' => 'order-courier-count-vip',
                    ];
                } else {
                    $customerLifetimeBadges[$phone] = [
                        'label' => 'Regula-' . $total,
                        'class' => 'order-courier-count-regular',
                    ];
                }
            }
        }

        $adminNotePresets = $this->getAdminNotePresetOptions();

        return view('backEnd.order.index', compact('show_data', 'order_status', 'users', 'steadfast', 'pathaostore', 'pathaocities', 'blockedIps', 'pathao_info', 'redx_info', 'paperfly_info', 'redxAreas', 'redxPickupStores', 'orderstatus', 'topStatusCards', 'customerLifetimeBadges', 'persistedFraudSummaries', 'allOrdersCount', 'filterCourierOptions', 'districtOptions', 'orderSourceFilterOptions', 'adminNotePresets'));
    }

    public function pathaocity(Request $request)
    {
        $pathao_info = Courierapi::where(['status' => 1, 'type' => 'pathao'])
            ->select('id', 'type', 'url', 'token', 'status')->first();

        if ($pathao_info && $pathao_info->token && $request->city_id) {
            // Clean up URL - remove trailing slashes and /aladdin if present
            $baseUrl = rtrim($pathao_info->url, '/');
            $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pathao_info->token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ])->get($baseUrl . '/aladdin/api/v1/cities/' . $request->city_id . '/zone-list');
            
            $pathaozones = $response->json();
            return response()->json($pathaozones);
        } else {
            return response()->json([
                'message' => 'Pathao configuration not found or token missing',
                'type' => 'error',
                'code' => 400,
                'data' => []
            ], 400);
        }
    }

    public function pathaozone(Request $request)
    {
        $pathao_info = Courierapi::where(['status' => 1, 'type' => 'pathao'])
            ->select('id', 'type', 'url', 'token', 'status')->first();

        if ($pathao_info && $pathao_info->token && $request->zone_id) {
            // Clean up URL - remove trailing slashes and /aladdin if present
            $baseUrl = rtrim($pathao_info->url, '/');
            $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $pathao_info->token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ])->get($baseUrl . '/aladdin/api/v1/zones/' . $request->zone_id . '/area-list');
            
            $pathaoareas = $response->json();
            return response()->json($pathaoareas);
        } else {
            return response()->json([
                'message' => 'Pathao configuration not found or token missing',
                'type' => 'error',
                'code' => 400,
                'data' => []
            ], 400);
        }
    }

    /**
     * Get RedX Areas (AJAX)
     */
    public function redxAreas(Request $request)
    {
        $redx_info = Courierapi::where(['status' => 1, 'type' => 'redx'])->first();

        if (!$redx_info || !$redx_info->token) {
            return response()->json([
                'status' => 'error',
                'message' => 'RedX configuration not found or token missing',
            ], 400);
        }

        try {
            $redxService = new RedXService();
            
            $postCode = $request->input('post_code');
            $districtName = $request->input('district_name');
            
            $result = $redxService->getAreas($postCode, $districtName);
            
            if ($result && isset($result['areas'])) {
                return response()->json([
                    'status' => 'success',
                    'areas' => $result['areas']
                ]);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch areas'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get RedX Pickup Stores (AJAX)
     */
    public function redxPickupStores(Request $request)
    {
        $redx_info = Courierapi::where(['status' => 1, 'type' => 'redx'])->first();

        if (!$redx_info || !$redx_info->token) {
            return response()->json([
                'status' => 'error',
                'message' => 'RedX configuration not found or token missing',
            ], 400);
        }

        try {
            $redxService = new RedXService();
            $result = $redxService->getPickupStores();
            
            if ($result && isset($result['pickup_stores'])) {
                return response()->json([
                    'status' => 'success',
                    'pickup_stores' => $result['pickup_stores']
                ]);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch pickup stores'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function order_pathao(Request $request)
    {
        // Handle both array and comma-separated string
        $orders_id = isset($request->order_ids) ? $request->order_ids : [];
        if (is_string($orders_id)) {
            $orders_id = array_filter(array_map('trim', explode(',', $orders_id)));
        }
        if (!is_array($orders_id)) {
            $orders_id = [];
        }

        if (empty($orders_id)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No orders selected.'
            ], 400);
        }

        $pathao_info = Courierapi::where(['status' => 1, 'type' => 'pathao'])->first();

        if (!$pathao_info) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pathao courier not configured.'
            ], 400);
        }
        
        // Token নেই বা expired হলে নতুন token generate করুন
        if (empty($pathao_info->token) && !empty($pathao_info->client_id) && !empty($pathao_info->client_secret)) {
            try {
                // Clean up URL
                $apiUrl = isset($pathao_info->url) ? $pathao_info->url : 'https://api-hermes.pathao.com';
                $apiUrl = rtrim($apiUrl, '/');
                $apiUrl = preg_replace('#/aladdin/?$#', '', $apiUrl);
                
                $tokenResponse = $this->generatePathaoToken(
                    $pathao_info->client_id,
                    $pathao_info->client_secret,
                    $apiUrl,
                    $pathao_info->username,
                    $pathao_info->password
                );
                
                if ($tokenResponse && isset($tokenResponse['access_token'])) {
                    $pathao_info->token = $tokenResponse['access_token'];
                    $pathao_info->save();
                }
            } catch (\Exception $e) {
                \Log::error('Pathao token generation failed: ' . $e->getMessage());
            }
        }
        
        if (empty($pathao_info->token)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pathao access token not available. Please generate token first.'
            ], 400);
        }

        $storeId = $request->pathaostore;

        if (empty($storeId)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please select a Pathao store.',
            ], 422);
        }

        $results = ['success' => [], 'failed' => []];

        foreach ($orders_id as $order_id) {
            $order = Order::with(['shipping', 'customer'])->find($order_id);
            if (!$order) {
                $results['failed'][] = ['order_id' => $order_id, 'message' => 'Order not found'];
                continue;
            }

            try {
                $manualPathaoLocation = $this->resolvePathaoOrderLocation($pathao_info, $order, $request);

                // Clean up URL - remove trailing slashes and /aladdin if present
                $baseUrl = rtrim($pathao_info->url, '/');
                $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);

                $recipientAddress = $this->buildPathaoRecipientAddress($order);
                if ($recipientAddress === '') {
                    $results['failed'][] = [
                        'order_id' => $order_id,
                        'message' => 'Customer address is missing for Pathao.',
                    ];
                    continue;
                }

                $payload = [
                    'store_id'           => $storeId,
                    'merchant_order_id'  => $order->invoice_id,
                    'sender_name'        => 'Test',
                    'sender_phone'       => $order->shipping ? $order->shipping->phone : '',
                    'recipient_name'     => $order->shipping ? $order->shipping->name : '',
                    'recipient_phone'    => $order->shipping ? $order->shipping->phone : '',
                    'recipient_address'  => $recipientAddress,
                    'delivery_type'      => 48,
                    'item_type'          => 2,
                    'special_instruction'=> 'Special note- product must be check after delivery',
                    'item_quantity'      => 1,
                    'item_weight'        => 0.5,
                    'amount_to_collect'  => round($order->amount),
                    'item_description'   => 'Special note- product must be check after delivery',
                ];

                if ($manualPathaoLocation['city_id'] && $manualPathaoLocation['zone_id'] && $manualPathaoLocation['area_id']) {
                    $payload['recipient_city'] = $manualPathaoLocation['city_id'];
                    $payload['recipient_zone'] = $manualPathaoLocation['zone_id'];
                    $payload['recipient_area'] = $manualPathaoLocation['area_id'];
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $pathao_info->token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ])->post($baseUrl . '/aladdin/api/v1/orders', $payload);

                if ($response->successful()) {
                    $res = $response->json();
                    $consignmentId = isset($res['data']['consignment_id']) ? $res['data']['consignment_id'] : (isset($res['consignment']['consignment_id']) ? $res['consignment']['consignment_id'] : (isset($res['consignment_id']) ? $res['consignment_id'] : null));
                    if ($consignmentId) {
                        $order->courier_type = 'pathao';
                        $order->courier_tracking_id = $consignmentId;
                        $order->courier_sent_at = now();
                        $order->consignment_id = $consignmentId;
                        $order->order_status = $this->resolveCourierInStatusId();
                        $order->save();

                        $results['success'][] = [
                            'order_id' => $order_id,
                            'consignment_id' => $consignmentId,
                        ];
                    } else {
                        $results['failed'][] = [
                            'order_id' => $order_id,
                            'message' => 'No consignment id in response',
                            'raw' => $res,
                        ];
                    }
                } else {
                    $results['failed'][] = [
                        'order_id' => $order_id,
                        'http_status' => $response->status(),
                        'body' => $response->body(),
                    ];
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'order_id' => $order_id,
                    'message'  => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'result' => $results,
        ]);
    }

    private function resolvePathaoOrderLocation($pathaoInfo, Order $order, Request $request): array
    {
        $manualCityId = $request->input('pathaocity');
        $manualZoneId = $request->input('pathaozone');
        $manualAreaId = $request->input('pathaoarea');

        if ($manualCityId && $manualZoneId && $manualAreaId) {
            return [
                'city_id' => $manualCityId,
                'zone_id' => $manualZoneId,
                'area_id' => $manualAreaId,
                'message' => null,
            ];
        }

        $shippingArea = trim((string) optional($order->shipping)->area);
        $shippingAddress = trim((string) optional($order->shipping)->address);
        $customerArea = trim((string) optional($order->customer)->area);
        $customerAddress = trim((string) optional($order->customer)->address);
        $customerDistrict = trim((string) optional($order->customer)->district);

        $keywords = $this->buildPathaoKeywords([
            $shippingArea,
            $customerDistrict,
            $customerArea,
            $shippingAddress,
            $customerAddress,
        ]);

        if (empty($keywords)) {
            return [
                'city_id' => null,
                'zone_id' => null,
                'area_id' => null,
                'message' => 'Customer address/area is missing for Pathao.',
            ];
        }

        $cityId = $manualCityId;
        if (!$cityId) {
            $cities = $this->fetchPathaoCities($pathaoInfo);
            $city = $this->findPathaoMatch($cities, ['city_name'], $keywords);
            if (!$city) {
                $city = $this->guessPathaoCityByAlias($cities, $keywords);
            }
            $cityId = $city['city_id'] ?? null;
        }

        if (!$cityId) {
            return [
                'city_id' => null,
                'zone_id' => null,
                'area_id' => null,
                'message' => 'Pathao city could not be matched for order #' . $order->invoice_id,
            ];
        }

        $zoneId = $manualZoneId;
        $zone = null;
        if (!$zoneId) {
            $zones = $this->fetchPathaoZones($pathaoInfo, $cityId);
            $zone = $this->findPathaoMatch($zones, ['zone_name'], $keywords);
            $zoneId = $zone['zone_id'] ?? null;
        }

        if (!$zoneId) {
            return [
                'city_id' => $cityId,
                'zone_id' => null,
                'area_id' => null,
                'message' => 'Pathao zone could not be matched for order #' . $order->invoice_id,
            ];
        }

        $areaId = $manualAreaId;
        if (!$areaId) {
            $areas = $this->fetchPathaoAreas($pathaoInfo, $zoneId);
            if ($zone && !empty($zone['zone_name'])) {
                $area = $this->findPathaoMatch($areas, ['area_name'], [$zone['zone_name']]);
                $areaId = $area['area_id'] ?? null;
            }
        }

        if (!$areaId) {
            $areas = isset($areas) ? $areas : $this->fetchPathaoAreas($pathaoInfo, $zoneId);
            $area = $this->findPathaoMatch($areas, ['area_name'], $keywords);
            $areaId = $area['area_id'] ?? null;
        }

        if (!$areaId) {
            return [
                'city_id' => $cityId,
                'zone_id' => $zoneId,
                'area_id' => null,
                'message' => 'Pathao area could not be matched for order #' . $order->invoice_id,
            ];
        }

        return [
            'city_id' => $cityId,
            'zone_id' => $zoneId,
            'area_id' => $areaId,
            'message' => null,
        ];
    }

    private function buildPathaoRecipientAddress(Order $order): string
    {
        $parts = [
            trim((string) optional($order->shipping)->address),
            trim((string) optional($order->customer)->area),
            trim((string) optional($order->customer)->district),
        ];

        $parts = array_values(array_unique(array_filter($parts, function ($part) {
            return $part !== '';
        })));

        return implode(', ', $parts);
    }

    private function fetchPathaoCities($pathaoInfo): array
    {
        return Cache::remember('pathao_bulk_city_list', 600, function () use ($pathaoInfo) {
            try {
                $baseUrl = rtrim($pathaoInfo->url, '/');
                $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);

                $response = Http::timeout(20)->retry(2, 500)->withHeaders([
                    'Authorization' => 'Bearer ' . $pathaoInfo->token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->get($baseUrl . '/aladdin/api/v1/city-list');

                return $response->json('data.data', []);
            } catch (\Exception $e) {
                Log::error('Pathao city auto-fetch failed', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    private function fetchPathaoZones($pathaoInfo, $cityId): array
    {
        return Cache::remember('pathao_bulk_zone_list_' . $cityId, 600, function () use ($pathaoInfo, $cityId) {
            try {
                $baseUrl = rtrim($pathaoInfo->url, '/');
                $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);

                $response = Http::timeout(20)->retry(2, 500)->withHeaders([
                    'Authorization' => 'Bearer ' . $pathaoInfo->token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->get($baseUrl . '/aladdin/api/v1/cities/' . $cityId . '/zone-list');

                return $response->json('data.data', []);
            } catch (\Exception $e) {
                Log::error('Pathao zone auto-fetch failed', ['city_id' => $cityId, 'error' => $e->getMessage()]);
                return [];
            }
        });
    }

    private function fetchPathaoAreas($pathaoInfo, $zoneId): array
    {
        return Cache::remember('pathao_bulk_area_list_' . $zoneId, 600, function () use ($pathaoInfo, $zoneId) {
            try {
                $baseUrl = rtrim($pathaoInfo->url, '/');
                $baseUrl = preg_replace('#/aladdin/?$#', '', $baseUrl);

                $response = Http::timeout(20)->retry(2, 500)->withHeaders([
                    'Authorization' => 'Bearer ' . $pathaoInfo->token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->get($baseUrl . '/aladdin/api/v1/zones/' . $zoneId . '/area-list');

                return $response->json('data.data', []);
            } catch (\Exception $e) {
                Log::error('Pathao area auto-fetch failed', ['zone_id' => $zoneId, 'error' => $e->getMessage()]);
                return [];
            }
        });
    }

    private function findPathaoMatch(array $items, array $nameKeys, array $keywords): ?array
    {
        $cleanKeywords = $this->buildPathaoKeywords($keywords);

        if (empty($cleanKeywords) || empty($items)) {
            return null;
        }

        $bestItem = null;
        $bestScore = 0;

        foreach ($items as $item) {
            foreach ($nameKeys as $nameKey) {
                $value = $this->normalizePathaoText($item[$nameKey] ?? '');
                if ($value === '') {
                    continue;
                }

                $score = $this->scorePathaoMatch($value, $cleanKeywords);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestItem = $item;
                }
            }
        }

        return $bestScore > 0 ? $bestItem : null;
    }

    private function normalizePathaoText($value): string
    {
        $value = (string) $value;
        $value = strtr($value, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function buildPathaoKeywords(array $keywords): array
    {
        $builtKeywords = [];

        foreach ($keywords as $keyword) {
            $normalized = $this->normalizePathaoText($keyword);
            if ($normalized === '') {
                continue;
            }

            $normalized = str_replace([
                'dhaka city',
                'inside dhaka',
                'within dhaka',
                'dhakar vitore',
                'dhakar bhetore',
            ], 'dhaka', $normalized);

            $normalized = preg_replace('/\b(inside|within|vitore|bhetore|taka|tk|cash|shipping|delivery|charge|fee|regular|inside dhaka)\b/', ' ', $normalized);
            $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

            if ($normalized === '') {
                continue;
            }

            $builtKeywords[] = $normalized;
            $builtKeywords = array_merge($builtKeywords, $this->expandPathaoKeywordAliases($normalized));

            foreach (explode(' ', $normalized) as $part) {
                if (strlen($part) >= 2 && !is_numeric($part)) {
                    $builtKeywords[] = $part;
                    $builtKeywords = array_merge($builtKeywords, $this->expandPathaoKeywordAliases($part));
                }
                if (is_numeric($part)) {
                    $builtKeywords[] = $part;
                }
            }

            if (str_contains($normalized, 'mirpur 11')) {
                $builtKeywords[] = 'mirpur';
                $builtKeywords[] = '11';
            }
        }

        return array_values(array_unique(array_filter($builtKeywords)));
    }

    private function expandPathaoKeywordAliases(string $keyword): array
    {
        $aliases = [
            'dhaka' => ['ঢাকা', 'dhaka city', 'inside dhaka', 'within dhaka'],
            'chittagong' => ['চট্টগ্রাম', 'চট্রগ্রাম', 'ctg', 'chattogram'],
            'cumilla' => ['কুমিল্লা', 'comilla'],
            'barisal' => ['বরিশাল', 'barishal'],
            'khulna' => ['খুলনা'],
            'gazipur' => ['গাজীপুর', 'tongi', 'টঙ্গী'],
            'narayanganj' => ['নারায়ণগঞ্জ', 'নারায়ণগঞ্জ'],
            'mymensingh' => ['ময়মনসিংহ', 'ময়মনসিংহ'],
            'rajshahi' => ['রাজশাহী'],
            'rangpur' => ['রংপুর'],
            'sylhet' => ['সিলেট'],
            'bogura' => ['বগুড়া', 'বগুড়া', 'bogra'],
            'mirpur' => ['মিরপুর'],
            'uttara' => ['উত্তরা'],
            'mohammadpur' => ['মোহাম্মদপুর'],
            'dhanmondi' => ['ধানমন্ডি'],
            'gulshan' => ['গুলশান'],
            'banani' => ['বনানী'],
            'badda' => ['বাড্ডা'],
            'pallabi' => ['পল্লবী'],
            'savar' => ['সাভার'],
            'jatrabari' => ['যাত্রাবাড়ী', 'যাত্রাবাড়ী'],
            'demra' => ['ডেমরা'],
        ];

        $expanded = [];
        foreach ($aliases as $canonical => $variants) {
            $normalizedCanonical = $this->normalizePathaoText($canonical);
            $normalizedVariants = array_map(function ($variant) {
                return $this->normalizePathaoText($variant);
            }, $variants);

            if ($keyword === $normalizedCanonical || in_array($keyword, $normalizedVariants, true)) {
                $expanded[] = $normalizedCanonical;
                $expanded = array_merge($expanded, $normalizedVariants);
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    private function scorePathaoMatch(string $value, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if ($value === $keyword) {
                $score = max($score, 100);
                continue;
            }

            if (str_contains($value, $keyword)) {
                $score = max($score, 70 + min(strlen($keyword), 20));
            }

            if (str_contains($keyword, $value)) {
                $score = max($score, 60 + min(strlen($value), 20));
            }

            $valueParts = explode(' ', $value);
            $keywordParts = explode(' ', $keyword);
            $matchedParts = array_intersect($valueParts, $keywordParts);
            if (!empty($matchedParts)) {
                $score = max($score, count($matchedParts) * 20);
            }
        }

        return $score;
    }

    private function guessPathaoCityByAlias(array $cities, array $keywords): ?array
    {
        $cityAliases = [
            'dhaka' => ['dhaka', 'dhanmondi', 'mirpur', 'uttara', 'mohammadpur', 'badda', 'gulshan', 'banani', 'savar', 'pallabi'],
            'chittagong' => ['chittagong', 'ctg', 'agrabad', 'halishahar'],
            'cumilla' => ['cumilla', 'comilla'],
            'barisal' => ['barisal', 'বরিশাল'],
            'khulna' => ['khulna'],
            'gazipur' => ['gazipur', 'tongi'],
            'narayanganj' => ['narayanganj'],
        ];

        foreach ($cities as $city) {
            $cityName = $this->normalizePathaoText($city['city_name'] ?? '');
            if ($cityName === '') {
                continue;
            }

            foreach ($cityAliases as $canonical => $aliases) {
                if ($cityName !== $canonical) {
                    continue;
                }

                foreach ($keywords as $keyword) {
                    foreach ($aliases as $alias) {
                        if (str_contains($keyword, $alias)) {
                            return $city;
                        }
                    }
                }
            }
        }

        return null;
    }
    
    /**
     * Generate Pathao Access Token
     */
    private function generatePathaoToken($clientId, $clientSecret, $baseUrl = 'https://api-hermes.pathao.com')
    {
        try {
            // Method 1: Try standard OAuth endpoint
            $response = Http::asForm()->post($baseUrl . '/aladdin/api/v1/issue-token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $clientId,
                'password' => $clientSecret,
                'grant_type' => 'password'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    return $data;
                }
            }
            
            // Method 2: Try alternative endpoint
            $response2 = Http::asForm()->post($baseUrl . '/aladdin/api/v1/authentication/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
            
            if ($response2->successful()) {
                $data = $response2->json();
                if (isset($data['access_token'])) {
                    return $data;
                }
            }
            
            // Method 3: Try with JSON
            $response3 = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($baseUrl . '/aladdin/api/v1/issue-token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials'
            ]);
            
            if ($response3->successful()) {
                $data = $response3->json();
                if (isset($data['access_token'])) {
                    return $data;
                }
            }
            
            throw new \Exception('Token generation failed. Please check your credentials.');
        } catch (\Exception $e) {
            \Log::error('Pathao token generation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE / PROCESS
    |--------------------------------------------------------------------------
    */

    public function invoice($invoice_id)
    {
        $order = Order::where(['invoice_id' => $invoice_id])
            ->with(['orderdetails', 'orderdetails.size', 'orderdetails.color', 'payment', 'shipping', 'customer', 'orderHistories.actor', 'orderHistories.status'])
            ->firstOrFail();

        $orderstatus = OrderStatus::all();

        return view('backEnd.order.invoice', compact('order', 'orderstatus'));
    }

    public function process($invoice_id)
    {
        $data = Order::where(['invoice_id' => $invoice_id])
            ->select('id', 'invoice_id', 'order_status')
            ->with(['orderdetails', 'orderdetails.size', 'orderdetails.color'])
            ->first();

        $shippingcharge = ShippingCharge::where('status', 1)->get();

        return view('backEnd.order.process', compact('data', 'shippingcharge'));
    }

    /**
     * Update single order status via AJAX (from invoice page)
     */
    public function updateSingleStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_status' => 'required|exists:order_statuses,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $shippingBefore = Shipping::where('order_id', $order->id)->first();
        $beforeSnapshot = $this->buildOrderHistorySnapshot($order, $shippingBefore);
        $orderSource = trim((string) $request->order_source);
        $courierNote = trim((string) $request->courier_note);
        $originalOrderStatus = (int) $order->order_status;
        $oldStatus = (int) $order->order_status;
        $newStatus = (int) $request->order_status;

        $order->order_status = $newStatus;
        $order->save();

        $this->recordOrderHistory(
            $order,
            'status_change',
            'Status Changed',
            'Order status was updated from invoice view.',
            [
                'Status' => [
                    'old' => $this->resolveOrderStatusName($oldStatus),
                    'new' => $this->resolveOrderStatusName($newStatus),
                ],
            ],
            $newStatus,
            Auth::guard('admin')->id() ?: auth()->id(),
            null,
            'admin'
        );

        // Handle fund transaction if status changed to completed (6)
        if ($newStatus == 6 && $oldStatus != 6) {
            FundTransaction::create([
                'direction'  => 'in',
                'source'     => 'sale',
                'source_id'  => $order->id,
                'amount'     => $order->amount,
                'note'       => 'Order complete (#' . $order->invoice_id . ') - Manual update',
                'created_by' => auth()->id(),
            ]);

            // Credit vendors for their items
            $this->distributeVendorEarnings($order);
            
            // Credit reseller wallet if this is a reseller order
            $this->creditResellerWallet($order);
        }

        // Handle stock change
        $this->handleStockChange($order, $oldStatus, $newStatus);
        $this->maybeSendFacebookCapiPurchaseForStatus($order, $newStatus);

        \Log::info('Order status manually updated', [
            'order_id' => $order->id,
            'invoice_id' => $order->invoice_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order status updated successfully',
            'order_status' => $newStatus,
            'order_status_name' => isset($order->status->name) ? $order->status->name : 'N/A',
        ]);
    }

    public function order_process(Request $request)
    {
        $link = OrderStatus::find($request->status)->slug;

        $order     = Order::find($request->id);
        $oldStatus = (int) $order->order_status;
        $newStatus = (int) $request->status;
        $shippingBefore = Shipping::where('order_id', $order->id)->first();
        $beforeSnapshot = $this->buildOrderHistorySnapshot($order, $shippingBefore);

        $order->order_status = $newStatus;
        $order->admin_note   = $request->admin_note;

        if ($newStatus == 6 && $oldStatus != 6) {
            FundTransaction::create([
                'direction'  => 'in',
                'source'     => 'sale',
                'source_id'  => $order->id,
                'amount'     => $order->amount,
                'note'       => 'Order complete (#' . $order->invoice_id . ') via process page',
                'created_by' => auth()->id(),
            ]);

            // Credit vendors for their items
            $this->distributeVendorEarnings($order);
            
            // Credit reseller wallet if this is a reseller order
            $this->creditResellerWallet($order);
        }

        $order->save();

        // স্টক হ্যান্ডেল
        $this->handleStockChange($order, $oldStatus, $newStatus);
        $this->maybeSendFacebookCapiPurchaseForStatus($order, $newStatus);

        $shipping_update = Shipping::where('order_id', $order->id)->first();
        $shippingfee     = ShippingCharge::find($request->area);

        if ($shippingfee && ($shippingfee->name != $request->area)) {
            $total                = $order->amount + ($shippingfee->amount - $order->shipping_charge);
            $order->shipping_charge = $shippingfee->amount;
            $order->amount          = $total;
            $order->save();
        }

        if ($shipping_update) {
            $shipping_update->name    = $request->name;
            $shipping_update->phone   = $request->phone;
            $shipping_update->address = $request->address;
            $shipping_update->area    = isset($shippingfee->name) ? $shippingfee->name : $shipping_update->area;
            $shipping_update->save();
        }

        $order->setRelation('shipping', $shipping_update);
        $afterSnapshot = $this->buildOrderHistorySnapshot($order, $shipping_update);
        $changes = $this->buildOrderHistoryChanges($beforeSnapshot, $afterSnapshot);
        if ($oldStatus !== $newStatus) {
            $changes['Status'] = [
                'old' => $this->resolveOrderStatusName($oldStatus),
                'new' => $this->resolveOrderStatusName($newStatus),
            ];
        }

        if (!empty($changes) || trim((string) $request->admin_note) !== '') {
            $this->recordOrderHistory(
                $order,
                'process_update',
                'Order Updated',
                trim((string) $request->admin_note) !== '' ? 'Order was updated from process page. Admin note: ' . trim((string) $request->admin_note) : 'Order was updated from process page.',
                $changes,
                (int) $order->order_status,
                Auth::guard('admin')->id() ?: auth()->id(),
                null,
                'admin'
            );
        }

        if ($newStatus == 5 && $oldStatus != 5) {
            $courier_info = Courierapi::where(['status' => 1, 'type' => 'steadfast'])->first();
            if ($courier_info) {
                $consignmentData = [
                    'invoice'          => $order->invoice_id,
                    'recipient_name'   => $order->shipping ? $order->shipping->name : 'InboxHat',
                    'recipient_phone'  => $order->shipping ? $order->shipping->phone : '01750578495',
                    'recipient_address'=> $order->shipping ? $order->shipping->address : '01750578495',
                    'cod_amount'       => $order->amount
                ];
                $client   = new Client();
                $response = $client->post($courier_info->url, [
                    'json'    => $consignmentData,
                    'headers' => [
                        'Api-Key'    => $courier_info->api_key,
                        'Secret-Key' => $courier_info->secret_key,
                        'Accept'     => 'application/json',
                    ],
                ]);

                $responseData = json_decode($response->getBody(), true);
                
                // Save courier information
                if ($responseData) {
                    $consignment_id = null;
                    if (isset($responseData['consignment']['consignment_id']) && $responseData['consignment']['consignment_id']) {
                        $consignment_id = $responseData['consignment']['consignment_id'];
                    } elseif (isset($responseData['data']['consignment_id']) && $responseData['data']['consignment_id']) {
                        $consignment_id = $responseData['data']['consignment_id'];
                    } elseif (isset($responseData['consignment_id']) && $responseData['consignment_id']) {
                        $consignment_id = $responseData['consignment_id'];
                    } elseif (isset($responseData['consignment']['id']) && $responseData['consignment']['id']) {
                        $consignment_id = $responseData['consignment']['id'];
                    } elseif (isset($responseData['data']['id']) && $responseData['data']['id']) {
                        $consignment_id = $responseData['data']['id'];
                    } elseif (isset($responseData['id']) && $responseData['id']) {
                        $consignment_id = $responseData['id'];
                    } elseif (isset($responseData['tracking_id']) && $responseData['tracking_id']) {
                        $consignment_id = $responseData['tracking_id'];
                    } elseif (isset($responseData['data']['tracking_id']) && $responseData['data']['tracking_id']) {
                        $consignment_id = $responseData['data']['tracking_id'];
                    } elseif (isset($responseData['consignment']['tracking_id']) && $responseData['consignment']['tracking_id']) {
                        $consignment_id = $responseData['consignment']['tracking_id'];
                    }
                    
                    if ($consignment_id) {
                        $order->courier_type = 'steadfast';
                        $order->courier_tracking_id = (string) $consignment_id;
                        $order->courier_sent_at = now();
                        $order->consignment_id = (string) $consignment_id; // Keep for backward compatibility
                        $order->save();
                        
                        \Log::info('Steadfast courier info saved from order_status_change', [
                            'order_id' => $order->id,
                            'tracking_id' => $consignment_id
                        ]);
                    }
                }
            }
        }

        Toastr::success('Success', 'Order status change successfully');
        return redirect('admin/order/' . $link);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE / BULK DELETE
    |--------------------------------------------------------------------------
    */

    protected function markOrdersAsPrinted(array $orderIds): void
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($orderIds)) {
            return;
        }

        $orders = Order::whereIn('id', $orderIds)->get(['id', 'admin_note']);

        foreach ($orders as $order) {
            $adminNote = (string) ($order->admin_note ?? '');
            if (str_contains($adminNote, self::PRINTED_MARKER)) {
                continue;
            }

            $adminNote = trim($adminNote);
            $order->admin_note = $adminNote === ''
                ? self::PRINTED_MARKER
                : $adminNote . PHP_EOL . self::PRINTED_MARKER;
            $order->save();
        }
    }

    public function destroy(Request $request)
    {
        Order::where('id', $request->id)->delete();
        OrderDetails::where('order_id', $request->id)->delete();
        Shipping::where('order_id', $request->id)->delete();
        Payment::where('order_id', $request->id)->delete();

        Toastr::success('Success', 'Order delete success successfully');
        return redirect()->back();
    }

    public function bulk_destroy(Request $request)
    {
        $orders_id = isset($request->order_ids) ? $request->order_ids : [];
        $cancelledStatusIds = OrderStatus::query()
            ->where(function ($query) {
                $query->where('slug', 'cancelled')
                    ->orWhere('name', 'like', '%cancel%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($orders_id)) {
            return response()->json(['status' => 'error', 'message' => 'Please select at least one order'], 422);
        }

        $invalidOrderExists = Order::whereIn('id', $orders_id)
            ->whereNotIn('order_status', $cancelledStatusIds)
            ->exists();

        if ($invalidOrderExists) {
            return response()->json(['status' => 'error', 'message' => 'Only cancelled orders can be deleted'], 422);
        }

        foreach ($orders_id as $order_id) {
            Order::where('id', $order_id)->delete();
            OrderDetails::where('order_id', $order_id)->delete();
            Shipping::where('order_id', $order_id)->delete();
            Payment::where('order_id', $order_id)->delete();
        }
        return response()->json(['status' => 'success', 'message' => 'Order delete successfully']);
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN / BULK COURIER / PRINT
    |--------------------------------------------------------------------------
    */

    public function order_assign(Request $request)
    {
        $orders = Order::whereIn('id', $request->input('order_ids', []))->get();
        $assignedUser = User::select('id', 'name')->find($request->user_id);

        foreach ($orders as $order) {
            $oldAssignedUser = $order->user_id ? User::select('id', 'name')->find($order->user_id) : null;
            $order->user_id = $request->user_id;
            $order->save();

            $this->recordOrderHistory(
                $order,
                'assign',
                'Order Assigned',
                'Order ownership/assignment was updated.',
                [
                    'Assigned User' => [
                        'old' => $oldAssignedUser ? $oldAssignedUser->name : 'Unassigned',
                        'new' => $assignedUser ? $assignedUser->name : 'Unassigned',
                    ],
                ],
                (int) $order->order_status,
                Auth::guard('admin')->id() ?: auth()->id(),
                null,
                'admin'
            );
        }

        return response()->json(['status' => 'success', 'message' => 'Order user id assign']);
    }

    // ✅ Bulk status change + stock handle
    public function order_status(Request $request)
    {
        // Check if this is AJAX request
        if (!$request->ajax() && !$request->wantsJson()) {
            // For non-AJAX requests, validate and return JSON anyway
        }
        
        // Manual validation to avoid redirect
        $orderStatus = $request->input('order_status');
        $orderIds = $request->input('order_ids', []);
        
        if (empty($orderStatus) || $orderStatus === '' || $orderStatus === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select a status',
                'errors' => ['order_status' => ['Please select a status']]
            ], 422);
        }
        
        if (empty($orderIds) || !is_array($orderIds) || count($orderIds) === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select at least one order',
                'errors' => ['order_ids' => ['Please select at least one order']]
            ], 422);
        }
        
        // Validate status exists
        $orderStatusModel = OrderStatus::find($orderStatus);
        if (!$orderStatusModel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Selected status is invalid',
                'errors' => ['order_status' => ['Selected status is invalid']]
            ], 422);
        }
        
        // Validate order IDs exist
        $validOrderIds = Order::whereIn('id', $orderIds)->pluck('id')->toArray();
        if (count($validOrderIds) !== count($orderIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more selected orders are invalid',
                'errors' => ['order_ids' => ['One or more selected orders are invalid']]
            ], 422);
        }
        
        $sms_gateway  = SmsGateway::where('status', 1)->first();
        $site_setting = GeneralSetting::where('status', 1)->first();

        $targetStatus = (int) $orderStatus;
        
        // Use validated order IDs
        $orderIdsToProcess = $validOrderIds;

        // ✅ Eager load customers to avoid N+1 query
        $orders = Order::whereIn('id', $orderIdsToProcess)
            ->with('customer:id,id,name,phone')
            ->get();

        foreach ($orders as $order) {

            $oldStatus = (int) $order->order_status;

            $order->order_status = $targetStatus;
            $order->update();

            $this->recordOrderHistory(
                $order,
                'status_change',
                'Status Changed',
                'Order status was updated in bulk action.',
                [
                    'Status' => [
                        'old' => $this->resolveOrderStatusName($oldStatus),
                        'new' => $this->resolveOrderStatusName($targetStatus),
                    ],
                ],
                $targetStatus,
                Auth::guard('admin')->id() ?: auth()->id(),
                null,
                'admin'
            );

            if ($targetStatus == 6 && $oldStatus != 6) {
                FundTransaction::create([
                    'direction'  => 'in',
                    'source'     => 'sale',
                    'source_id'  => $order->id,
                    'amount'     => $order->amount,
                    'note'       => 'Order complete (#' . $order->invoice_id . ')',
                    'created_by' => auth()->id(),
                ]);

                // Credit vendors for their items
                $this->distributeVendorEarnings($order);
                
                // Credit reseller wallet if this is a reseller order
                $this->creditResellerWallet($order);
            }

            // স্টক হ্যান্ডেল
            $this->handleStockChange($order, $oldStatus, $targetStatus);
            $this->maybeSendFacebookCapiPurchaseForStatus($order, $targetStatus);

            // ✅ Use eager loaded customer instead of find()
            if ($sms_gateway && $order->customer) {
                $url  = $sms_gateway->url;
                $data = [
                    "api_key"  => $sms_gateway->api_key,
                    "number"   => $order->customer->phone,
                    "type"     => 'text',
                    "senderid" => $sms_gateway->serderid,
                    "message"  => "Dear {$order->customer->name},\r\n"
                        . "Your order (Order ID: {$order->invoice_id}) status has been updated to: "
                        . "{$orderStatusModel->name}.\r\n"
                        . "Thank you for using " . (isset($site_setting->name) ? $site_setting->name : 'our service') . "!",
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Order status change successfully'
        ]);
    }

    public function order_print(Request $request)
    {
        $orderIds = array_filter((array) $request->input('order_ids', []));
        $this->markOrdersAsPrinted($orderIds);

        $orders = Order::whereIn('id', $orderIds)
            ->with('orderdetails.color', 'orderdetails.size', 'payment', 'shipping', 'customer')
            ->get();

        $view = view('backEnd.order.print', ['orders' => $orders])->render();
        return response()->json(['status' => 'success', 'view' => $view]);
    }

    public function order_pos_print(Request $request)
    {
        $orderIds = array_filter((array) $request->input('order_ids', []));

        if (empty($orderIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No orders selected for POS print.',
            ]);
        }

        $this->markOrdersAsPrinted($orderIds);

        $orders = Order::whereIn('id', $orderIds)
            ->with('orderdetails.product', 'orderdetails.color', 'orderdetails.size', 'shipping', 'customer', 'user.vendor')
            ->get();

        $view = view('backEnd.order.pos_print', ['orders' => $orders])->render();

        return response()->json([
            'status' => 'success',
            'view' => $view,
        ]);
    }

    public function order_export_csv(Request $request)
    {
        $orderIds = array_values(array_filter((array) $request->input('order_ids', [])));

        if (empty($orderIds)) {
            Toastr::error('Please select at least one order to export.');
            return redirect()->back();
        }

        $orders = Order::whereIn('id', $orderIds)
            ->with(['shipping', 'status', 'orderdetails'])
            ->orderByDesc('id')
            ->get();

        $fileName = 'selected-orders-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Invoice ID',
            'Order ID',
            'Customer Name',
            'Customer Phone',
            'Customer Address',
            'Area',
            'Product Summary',
            'Total Qty',
            'Product Amount',
            'Delivery Charge',
            'COD Amount',
            'Order Status',
            'Courier Type',
            'Note',
        ];

        return response()->streamDownload(function () use ($orders, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($orders as $order) {
                $shipping = $order->shipping;
                $productSummary = $order->orderdetails
                    ->map(function ($detail) {
                        $name = trim((string) ($detail->product_name ?? 'Product'));
                        $qty = (int) ($detail->qty ?? 0);
                        return $qty > 0 ? $name . ' x' . $qty : $name;
                    })
                    ->filter()
                    ->implode(' | ');

                $deliveryCharge = (float) ($order->shipping_charge ?? 0);
                $codAmount = (float) ($order->amount ?? 0);
                $productAmount = max(0, $codAmount - $deliveryCharge);
                $customerAddress = trim((string) ($shipping->address ?? ''));

                fputcsv($handle, [
                    (string) ($order->invoice_id ?? $order->id),
                    $order->id,
                    (string) ($shipping->name ?? 'Walk-in Customer'),
                    (string) ($shipping->phone ?? ''),
                    $customerAddress,
                    (string) ($shipping->area ?? ''),
                    $productSummary,
                    (int) $order->orderdetails->sum('qty'),
                    number_format($productAmount, 2, '.', ''),
                    number_format($deliveryCharge, 2, '.', ''),
                    number_format($codAmount, 2, '.', ''),
                    (string) (optional($order->status)->name ?? 'Unknown'),
                    (string) ($order->courier_type ?? ''),
                    trim(preg_replace('/\s+/', ' ', (string) ($order->note ?? ''))),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function bulk_courier($slug, Request $request)
    {
        $courier_info = Courierapi::where(['status' => 1, 'type' => $slug])->first();

        if (!$courier_info) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Courier information not found.'
            ]);
        }

        $orders_ids = isset($request->order_ids) ? $request->order_ids : [];
        if (is_string($orders_ids)) {
            $orders_ids = array_values(array_filter(array_map('trim', explode(',', $orders_ids))));
        }
        if (empty($orders_ids)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No orders selected.'
            ]);
        }

        $successOrders = [];
        $failedOrders  = [];

        foreach ($orders_ids as $order_id) {
            $order = Order::with('shipping', 'orderdetails')->find($order_id);
            if (!$order) continue;

            try {
                // RedX API uses different structure
                if ($slug === 'redx') {
                    // Verify RedX is configured
                    $redxConfig = Courierapi::where(['status' => 1, 'type' => 'redx'])->first();
                    if (!$redxConfig || empty($redxConfig->token)) {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'RedX API not configured or token missing. Please configure RedX in API Integration settings.',
                        ];
                        continue;
                    }
                    
                    $redxService = new RedXService();
                    
                    // Verify service initialized properly
                    if (!$redxService->isConfigured()) {
                        $configStatus = $redxService->getConfigStatus();
                        \Log::error('RedX Service not configured', [
                            'order_id' => $order_id,
                            'config_status' => $configStatus
                        ]);
                        
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'RedX service not configured. Please check API token and URL in settings.',
                        ];
                        continue;
                    }
                    
                    // Get delivery area ID from shipping area
                    // Note: You may need to map shipping area to RedX area_id
                    $deliveryAreaId = isset($request->delivery_area_id) ? $request->delivery_area_id : 1; // Default or from request
                    $pickupStoreId = isset($request->pickup_store_id) ? $request->pickup_store_id : null;
                    
                    // Calculate parcel weight (in grams)
                    $parcelWeight = 500; // Default 500g, you can calculate from order details
                    if ($order->orderdetails && $order->orderdetails->count() > 0) {
                        // Calculate weight from products if available
                        $parcelWeight = $order->orderdetails->sum(function($detail) {
                            return ((isset($detail->product) && isset($detail->product->weight) ? $detail->product->weight : 0) * $detail->qty);
                        });
                        if ($parcelWeight < 100) $parcelWeight = 500; // Minimum 500g
                    }
                    
                    // Prepare parcel details JSON
                    $parcelDetailsJson = [];
                    if ($order->orderdetails) {
                        foreach ($order->orderdetails as $detail) {
                            $parcelDetailsJson[] = [
                                'name' => isset($detail->product_name) ? $detail->product_name : 'Product',
                                'category' => (isset($detail->product) && isset($detail->product->category) && isset($detail->product->category->name) ? $detail->product->category->name : 'General'),
                                'value' => (int)(isset($detail->sale_price) ? $detail->sale_price : 0)
                            ];
                        }
                    }
                    
                    // Validate required fields
                    $customerName = trim(isset($order->shipping->name) ? $order->shipping->name : 'Unknown');
                    $customerPhone = trim(isset($order->shipping->phone) ? $order->shipping->phone : '00000000000');
                    $customerAddress = trim(isset($order->shipping->address) ? $order->shipping->address : 'No address');
                    
                    if (empty($customerName) || $customerName === 'Unknown') {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'Customer name is required',
                        ];
                        continue;
                    }
                    
                    if (empty($customerPhone) || $customerPhone === '00000000000') {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'Customer phone is required',
                        ];
                        continue;
                    }
                    
                    if (empty($customerAddress) || $customerAddress === 'No address') {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'Customer address is required',
                        ];
                        continue;
                    }
                    
                    $data = [
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                        'delivery_area' => isset($order->shipping->area) ? $order->shipping->area : 'Unknown',
                        'delivery_area_id' => (int)$deliveryAreaId,
                        'customer_address' => $customerAddress,
                        'merchant_invoice_id' => $order->invoice_id,
                        'cash_collection_amount' => (string)$order->amount,
                        'parcel_weight' => (string)$parcelWeight, // API expects string
                        'instruction' => isset($order->note) ? $order->note : '',
                        'value' => (string)$order->amount,
                    ];
                    
                    // Add parcel_details_json only if not empty
                    if (!empty($parcelDetailsJson)) {
                        $data['parcel_details_json'] = $parcelDetailsJson;
                    }
                    
                    if ($pickupStoreId) {
                        $data['pickup_store_id'] = $pickupStoreId;
                    }
                    
                    $result = $redxService->createParcel($data);
                    
                    \Log::info('RedX Create Parcel Response', [
                        'order_id' => $order_id,
                        'invoice_id' => $order->invoice_id,
                        'result' => $result
                    ]);
                    
                    if ($result && isset($result['tracking_id'])) {
                        $consignment_id = $result['tracking_id'];
                        
                        $order->courier_type = 'redx';
                        $order->courier_tracking_id = $consignment_id;
                        $order->courier_sent_at = now();
                        $order->consignment_id = $consignment_id;
                        $order->order_status = $this->resolveCourierInStatusId();
                        $order->save();
                        
                        \Log::info('✅ RedX parcel created successfully', [
                            'order_id' => $order_id,
                            'invoice_id' => $order->invoice_id,
                            'tracking_id' => $consignment_id
                        ]);
                        
                        $successOrders[] = [
                            'order_id' => $order_id,
                            'consignment_id' => $consignment_id,
                            'message' => 'RedX parcel created successfully',
                        ];
                    } else {
                        $errorMessage = 'Failed to create RedX parcel';
                        if (isset($result['error'])) {
                            $errorMessage .= ': ' . $result['error'];
                        }
                        if (isset($result['message'])) {
                            $errorMessage .= ' - ' . $result['message'];
                        }
                        if (isset($result['status'])) {
                            $errorMessage .= ' (Status: ' . $result['status'] . ')';
                        }
                        
                        \Log::error('❌ RedX parcel creation failed', [
                            'order_id' => $order_id,
                            'invoice_id' => $order->invoice_id,
                            'result' => $result,
                            'data_sent' => $data
                        ]);
                        
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => $errorMessage,
                            'details' => $result
                        ];
                    }
                    
                    continue; // Skip to next order
                }

                if ($slug === 'paperfly') {
                    if (empty($courier_info->api_key) || empty($courier_info->username) || empty($courier_info->password) || empty($courier_info->url)) {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'Paperfly API configuration incomplete. Please configure URL, API key, username and password.',
                        ];
                        continue;
                    }

                    $customerName = trim(isset($order->shipping->name) ? $order->shipping->name : '');
                    $customerPhone = trim(isset($order->shipping->phone) ? $order->shipping->phone : '');
                    $customerAddress = trim(isset($order->shipping->address) ? $order->shipping->address : '');

                    if ($customerName === '' || $customerPhone === '' || $customerAddress === '') {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => 'Customer name, phone and address are required for Paperfly.',
                        ];
                        continue;
                    }

                    $payload = $this->buildPaperflyOrderPayload($order);

                    $client = new \GuzzleHttp\Client();
                    $response = $client->post(trim($courier_info->url), [
                        'auth' => [$courier_info->username, $courier_info->password],
                        'json' => $payload,
                        'headers' => [
                            'paperflykey' => $courier_info->api_key,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                    ]);

                    $responseBody = $response->getBody()->getContents();
                    $res = json_decode($responseBody, true);

                    \Log::info('Paperfly Create Order Response', [
                        'order_id' => $order_id,
                        'invoice_id' => $order->invoice_id,
                        'payload' => $payload,
                        'response' => $res,
                        'status_code' => $response->getStatusCode(),
                        'raw_response' => $responseBody,
                    ]);

                    $consignment_id = $this->resolvePaperflyTrackingId($res);

                    if ($consignment_id) {
                        $order->courier_type = 'paperfly';
                        $order->courier_tracking_id = $consignment_id;
                        $order->courier_sent_at = now();
                        $order->consignment_id = $consignment_id;
                        $order->order_status = $this->resolveCourierInStatusId();
                        $order->save();

                        $successOrders[] = [
                            'order_id' => $order_id,
                            'consignment_id' => $consignment_id,
                            'message' => isset($res['success']['message']) ? $res['success']['message'] : 'Paperfly order created successfully',
                        ];
                    } else {
                        $failedOrders[] = [
                            'order_id' => $order_id,
                            'message' => isset($res['success']['message']) ? $res['success']['message'] : 'Paperfly response did not include tracking number.',
                            'response' => $res,
                        ];
                    }

                    continue;
                }
                
                // For other couriers (Steadfast, etc.)
                $data = [
                    'invoice'          => $order->invoice_id,
                    'recipient_name'   => isset($order->shipping->name) ? $order->shipping->name : 'Unknown',
                    'recipient_phone'  => isset($order->shipping->phone) ? $order->shipping->phone : '00000000000',
                    'recipient_address'=> isset($order->shipping->address) ? $order->shipping->address : 'No address',
                    'cod_amount'       => $order->amount,
                ];

                // Clean up URL - remove spaces and trailing slashes
                $apiUrl = trim($courier_info->url);
                $apiUrl = rtrim($apiUrl, '/');
                $apiUrl = str_replace(' ', '', $apiUrl); // Remove any spaces in URL
                
                $client   = new \GuzzleHttp\Client();
                $response = $client->post($apiUrl, [
                    'json'    => $data,
                    'headers' => [
                        'Api-Key'    => $courier_info->api_key,
                        'Secret-Key' => $courier_info->secret_key,
                        'Accept'     => 'application/json',
                    ],
                ]);

                // Get response body as string first
                $responseBody = $response->getBody()->getContents();
                $res = json_decode($responseBody, true);
                
                // Log full response for debugging
                \Log::info('Courier Response for ' . $slug, [
                    'order_id' => $order_id,
                    'invoice_id' => $order->invoice_id,
                    'response' => $res,
                    'response_keys' => is_array($res) ? array_keys($res) : 'not_array',
                    'status_code' => $response->getStatusCode(),
                    'raw_response' => $responseBody
                ]);

                // Try multiple ways to get consignment_id from Steadfast/RedX response
                $consignment_id = null;
                
                // Check various response structures
                if (is_array($res)) {
                    // Method 1: consignment.consignment_id
                    if (isset($res['consignment']['consignment_id'])) {
                        $consignment_id = $res['consignment']['consignment_id'];
                    }
                    // Method 2: data.consignment_id
                    elseif (isset($res['data']['consignment_id'])) {
                        $consignment_id = $res['data']['consignment_id'];
                    }
                    // Method 3: consignment_id (direct)
                    elseif (isset($res['consignment_id'])) {
                        $consignment_id = $res['consignment_id'];
                    }
                    // Method 4: consignment.id
                    elseif (isset($res['consignment']['id'])) {
                        $consignment_id = $res['consignment']['id'];
                    }
                    // Method 5: data.id
                    elseif (isset($res['data']['id'])) {
                        $consignment_id = $res['data']['id'];
                    }
                    // Method 6: id (direct)
                    elseif (isset($res['id'])) {
                        $consignment_id = $res['id'];
                    }
                    // Method 7: tracking_id
                    elseif (isset($res['tracking_id'])) {
                        $consignment_id = $res['tracking_id'];
                    }
                    // Method 8: data.tracking_id
                    elseif (isset($res['data']['tracking_id'])) {
                        $consignment_id = $res['data']['tracking_id'];
                    }
                    // Method 9: consignment.tracking_id
                    elseif (isset($res['consignment']['tracking_id'])) {
                        $consignment_id = $res['consignment']['tracking_id'];
                    }
                    // Method 10: Check if response has success and data structure
                    elseif (isset($res['success']) && isset($res['data'])) {
                        $consignment_id = isset($res['data']['consignment_id']) ? $res['data']['consignment_id'] : (isset($res['data']['id']) ? $res['data']['id'] : (isset($res['data']['tracking_id']) ? $res['data']['tracking_id'] : null));
                    }
                }

                // Convert to string if found
                if ($consignment_id !== null) {
                    $consignment_id = (string) $consignment_id;
                }

                if ($consignment_id) {
                    // Save courier information
                    $order->courier_type = $slug; // steadfast, redx, etc
                    $order->courier_tracking_id = $consignment_id;
                    $order->courier_sent_at = now();
                    $order->consignment_id = $consignment_id; // Keep for backward compatibility
                    $order->order_status   = $this->resolveCourierInStatusId();
                    $order->save();

                    \Log::info('✅ Courier info saved successfully', [
                        'order_id' => $order_id,
                        'invoice_id' => $order->invoice_id,
                        'courier_type' => $slug,
                        'tracking_id' => $consignment_id
                    ]);

                    $successOrders[] = [
                        'order_id'       => $order_id,
                        'consignment_id' => $consignment_id,
                        'message'        => isset($res['message']) ? $res['message'] : 'Order placed successfully',
                    ];
                } else {
                    // Log full response structure for debugging
                    \Log::error('❌ No consignment_id found in response', [
                        'order_id' => $order_id,
                        'invoice_id' => $order->invoice_id,
                        'courier' => $slug,
                        'response' => $res,
                        'response_structure' => is_array($res) ? json_encode($res, JSON_PRETTY_PRINT) : 'not_array'
                    ]);
                    
                    // Also return response in error message for debugging
                    $errorMessage = 'No consignment_id found in response. ';
                    if (is_array($res)) {
                        $errorMessage .= 'Response keys: ' . implode(', ', array_keys($res));
                    } else {
                        $errorMessage .= 'Response: ' . json_encode($res);
                    }
                    
                    $failedOrders[] = [
                        'order_id' => $order_id,
                        'message'  => $errorMessage,
                        'response' => $res,
                        'response_keys' => is_array($res) ? array_keys($res) : null,
                    ];
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                // Handle 4xx errors (401, 403, 404, etc.)
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                $responseBody = $response ? $response->getBody()->getContents() : '';
                $errorData = json_decode($responseBody, true);
                
                $errorMessage = $e->getMessage();
                if ($errorData && isset($errorData['message'])) {
                    $errorMessage = $errorData['message'];
                } elseif ($responseBody) {
                    $errorMessage = $responseBody;
                }
                
                \Log::error('Courier API Error (ClientException)', [
                    'order_id' => $order_id,
                    'courier' => $slug,
                    'status_code' => $statusCode,
                    'error_message' => $errorMessage,
                    'response_body' => $responseBody
                ]);
                
                $failedOrders[] = [
                    'order_id' => $order_id,
                    'message'  => $errorMessage . ' (Status: ' . $statusCode . ')',
                    'status_code' => $statusCode
                ];
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                // Handle 5xx errors
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                $responseBody = $response ? $response->getBody()->getContents() : '';
                
                \Log::error('Courier API Error (ServerException)', [
                    'order_id' => $order_id,
                    'courier' => $slug,
                    'status_code' => $statusCode,
                    'response_body' => $responseBody
                ]);
                
                $failedOrders[] = [
                    'order_id' => $order_id,
                    'message'  => 'Server error: ' . $e->getMessage() . ' (Status: ' . $statusCode . ')',
                    'status_code' => $statusCode
                ];
            } catch (\Exception $e) {
                \Log::error('Courier API Error (General)', [
                    'order_id' => $order_id,
                    'courier' => $slug,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $failedOrders[] = [
                    'order_id' => $order_id,
                    'message'  => $e->getMessage(),
                ];
            }
        }

        $successCount = count($successOrders);
        $failedCount = count($failedOrders);
        $responseStatus = 'success';
        $responseMessage = 'Courier processed successfully';

        if ($successCount === 0 && $failedCount > 0) {
            $responseStatus = 'error';
            $responseMessage = $failedOrders[0]['message'] ?? 'Courier request failed';
        } elseif ($successCount > 0 && $failedCount > 0) {
            $responseStatus = 'partial';
            $responseMessage = 'Some orders were sent, but some failed.';
        }

        // Return detailed response for debugging
        return response()->json([
            'status'  => $responseStatus,
            'message' => $responseMessage,
            'success' => $successOrders,
            'failed'  => $failedOrders,
            'debug' => [
                'courier_type' => $slug,
                'total_orders' => count($orders_ids),
                'success_count' => $successCount,
                'failed_count' => $failedCount
            ]
        ]);
    }

    private function resolveCourierInStatusId(): int
    {
        return $this->resolveOrderStatusId([
            'in-courier',
            'in_courier',
            'in courier',
        ], 5);
    }

    private function buildPaperflyOrderPayload($order): array
    {
        $productBrief = $order->orderdetails && $order->orderdetails->count() > 0
            ? $order->orderdetails->pluck('product_name')->filter()->implode(', ')
            : 'Order from SellwayBD';

        if ($productBrief === '') {
            $productBrief = 'Order from SellwayBD';
        }

        $totalWeight = 0;
        if ($order->orderdetails && $order->orderdetails->count() > 0) {
            $totalWeight = $order->orderdetails->sum(function ($detail) {
                $unitWeight = isset($detail->product) && isset($detail->product->weight) ? (float) $detail->product->weight : 0;
                return $unitWeight * (int) $detail->qty;
            });
        }

        if ($totalWeight <= 0) {
            $totalWeight = 0.5;
        }

        return [
            'merchantOrderReference' => (string) $order->invoice_id,
            'storeName' => config('app.name', 'SellwayBD'),
            'productBrief' => $productBrief,
            'packagePrice' => (string) round((float) $order->amount, 2),
            'max_weight' => (string) round($totalWeight, 2),
            'customerName' => trim((string) optional($order->shipping)->name),
            'customerAddress' => trim((string) optional($order->shipping)->address),
            'customerPhone' => trim((string) optional($order->shipping)->phone),
        ];
    }

    private function resolvePaperflyTrackingId($response): ?string
    {
        if (!is_array($response)) {
            return null;
        }

        $trackingId = null;

        if (isset($response['tracking_number']) && $response['tracking_number']) {
            $trackingId = $response['tracking_number'];
        } elseif (isset($response['tracking_barcode']) && $response['tracking_barcode']) {
            $trackingId = $response['tracking_barcode'];
        } elseif (isset($response['success']['tracking_number']) && $response['success']['tracking_number']) {
            $trackingId = $response['success']['tracking_number'];
        } elseif (isset($response['success']['tracking_barcode']) && $response['success']['tracking_barcode']) {
            $trackingId = $response['success']['tracking_barcode'];
        }

        return $trackingId ? (string) $trackingId : null;
    }

    private function resolveOrderStatusId(array $aliases, ?int $fallback = null): ?int
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

    /*
    |--------------------------------------------------------------------------
    | STOCK REPORT / ORDER REPORT
    |--------------------------------------------------------------------------
    */

    public function stock_report(Request $request)
    {
        $products = Product::select('id', 'name', 'new_price', 'stock')
            ->where('status', 1);

        if ($request->keyword) {
            $products = $products->where('name', 'LIKE', '%' . $request->keyword . "%");
        }
        if ($request->category_id) {
            $products = $products->where('category_id', $request->category_id);
        }
        if ($request->start_date && $request->end_date) {
            $products = $products->whereBetween('updated_at', [$request->start_date, $request->end_date]);
        }

        $total_purchase = $products->sum(\DB::raw('purchase_price * stock'));
        $total_stock    = $products->sum('stock');
        $total_price    = $products->sum(\DB::raw('new_price * stock'));

        $products   = $products->paginate(10);
        $categories = Category::where('status', 1)->get();

        return view('backEnd.reports.stock', compact(
            'products',
            'categories',
            'total_purchase',
            'total_stock',
            'total_price'
        ));
    }

    public function order_report(Request $request)
    {
        $users = User::where('status', 1)->get();

        $orders = OrderDetails::with('shipping', 'order')
            ->whereHas('order', function ($query) {
                $query->where('order_status', 6);
            });

        if ($request->keyword) {
            $orders = $orders->where('name', 'LIKE', '%' . $request->keyword . "%");
        }
        if ($request->user_id) {
            $orders = $orders->whereHas('order', function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            });
        }
        if ($request->start_date && $request->end_date) {
            $orders = $orders->whereBetween('updated_at', [$request->start_date, $request->end_date]);
        }

        $total_purchase = $orders->sum(\DB::raw('purchase_price * qty'));
        $total_item     = $orders->sum('qty');
        $total_sales    = $orders->sum(\DB::raw('sale_price * qty'));
        $orders         = $orders->paginate(10);

        return view('backEnd.reports.order', compact(
            'orders',
            'users',
            'total_purchase',
            'total_item',
            'total_sales'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | POS ORDER CREATE / UPDATE
    |--------------------------------------------------------------------------
    */

    public function productSearch(Request $request)
    {
        $term = trim((string) $request->get('name', ''));

        if ($term === '') {
            return response()->json([
                'message' => 'Search term is required.',
            ], 422);
        }

        if ($request->boolean('suggest')) {
            $products = Product::query()
                ->select('id', 'name', 'product_code', 'new_price', 'stock')
                ->where('status', 1)
                ->where(function ($query) use ($term) {
                    $query->where('name', 'LIKE', '%' . $term . '%')
                        ->orWhere('product_code', 'LIKE', '%' . $term . '%');
                })
                ->orderByRaw("CASE WHEN product_code = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END", [$term, $term . '%'])
                ->limit(8)
                ->get();

            return response()->json($products);
        }

        $product = Product::query()
            ->select('id', 'name', 'product_code', 'new_price', 'stock')
            ->where('status', 1)
            ->where(function ($query) use ($term) {
                $query->where('product_code', $term)
                    ->orWhere('name', 'LIKE', '%' . $term . '%');
            })
            ->orderByRaw("CASE WHEN product_code = ? THEN 0 ELSE 1 END", [$term])
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'product_code' => $product->product_code,
            'price' => $product->new_price,
            'stock' => $product->stock,
        ]);
    }

    public function order_create()
    {
        Cart::instance('pos_shopping')->destroy();

        // ✅ Limit products for POS dropdown to avoid memory issues
        $products = Product::select('id', 'name', 'new_price','stock', 'product_code')
            ->where(['status' => 1])
            ->limit(100)
            ->get();

        $cartinfo       = Cart::instance('pos_shopping')->content();
        $shippingcharge = ShippingCharge::where('status', 1)->get();
        $quickOrderStatuses = OrderStatus::query()
            ->whereIn('slug', ['new', 'pending', 'processing', 'paid-return'])
            ->orWhereIn('name', ['New', 'Pending', 'Processing', 'Approved', 'Paid Return'])
            ->orderByRaw("CASE
                WHEN LOWER(slug) = 'new' OR LOWER(name) = 'new' THEN 1
                WHEN LOWER(slug) = 'pending' OR LOWER(name) = 'pending' THEN 2
                WHEN LOWER(slug) = 'processing' OR LOWER(name) IN ('processing', 'approved') THEN 3
                WHEN LOWER(slug) IN ('paid-return', 'paid_return') OR LOWER(name) = 'paid return' THEN 4
                ELSE 5 END")
            ->get();

        return view('backEnd.order.create', compact(
            'products',
            'cartinfo',
            'shippingcharge',
            'quickOrderStatuses'
        ));
    }

    public function order_store(Request $request)
    {
        $this->validate($request, [
            'name'    => 'required|string',
            'phone'   => 'required|digits:11',
            'address' => 'required|string',
            'area'    => 'required',
            'order_status' => 'nullable|exists:order_statuses,id',
            'paid_return_amount' => 'nullable|numeric|min:0',
        ]);

        if (Cart::instance('pos_shopping')->count() <= 0) {
            Toastr::error('Please select at least one product.', 'Failed!');
            return redirect()->back()->withErrors(['product' => 'Please select at least one product.'])->withInput();
        }

        $subtotalRaw = Cart::instance('pos_shopping')->subtotal();
        $subtotal   = (float) preg_replace('/[^\d.]/', '', (string) $subtotalRaw);
        $couponDiscount = (float) (Session::get('pos_discount') ?? 0);
        $productDiscount = Cart::instance('pos_shopping')->content()->sum(function ($item) {
            return ((float) ($item->options->product_discount ?? 0)) * ((float) $item->qty);
        });
        $discount   = $couponDiscount + $productDiscount;
        $shippingfee = ShippingCharge::find($request->area);

        $exits_customer = Customer::where('phone', $request->phone)
            ->select('phone', 'id')->first();

        if ($exits_customer) {
            $customer_id = $exits_customer->id;
        } else {
            $password        = rand(111111, 999999);
            $store           = new Customer();
            $store->name     = $request->name;
            $store->slug     = $request->name;
            $store->phone    = $request->phone;
            $store->password = bcrypt($password);
            $store->verify   = 1;
            $store->status   = 'active';
            $store->save();
            $customer_id = $store->id;
        }

        $orderNote = trim((string) $request->note);

        $order                  = new Order();
        $order->invoice_id      = rand(11111, 99999);
        $order->amount          = ($subtotal + (isset($shippingfee->amount) ? $shippingfee->amount : 0)) - $discount;
        $order->discount        = $discount ? $discount : 0;
        $order->shipping_charge = isset($shippingfee->amount) ? $shippingfee->amount : 0;
        $order->customer_id     = $customer_id;
        $order->order_status    = (int) ($request->order_status ?: (OrderStatus::where('slug', 'new')->value('id') ?: 1));
        if (Schema::hasColumn('orders', 'paid_return_amount')) {
            $order->paid_return_amount = max(0, (float) $request->input('paid_return_amount', 0));
        }
        if (Schema::hasColumn('orders', 'created_by')) {
            $order->created_by = Auth::guard('admin')->id() ?: auth()->id();
        }
        if (Schema::hasColumn('orders', 'order_source_channel')) {
            $order->order_source_channel = 'created';
        }
        $order->note            = $orderNote;
        $order->save();

        $shipping              = new Shipping();
        $shipping->order_id    = $order->id;
        $shipping->customer_id = $customer_id;
        $shipping->name        = $request->name;
        $shipping->phone       = $request->phone;
        $shipping->address     = $request->address;
        $shipping->area        = isset($shippingfee->name) ? $shippingfee->name : '';
        $shipping->save();

        $payment                 = new Payment();
        $payment->order_id       = $order->id;
        $payment->customer_id    = $customer_id;
        $payment->payment_method = 'Cash On Delivery';
        $payment->amount         = $order->amount;
        $payment->payment_status = 'pending';
        $payment->save();

        foreach (Cart::instance('pos_shopping')->content() as $cart) {
            $sizeId   = $cart->options->size_id ?? null;
            $sizeName = $cart->options->product_size ?? null;
            $colorId   = $cart->options->color_id ?? null;
            $colorName = $cart->options->product_color ?? null;

            Log::channel('single')->info('[POS order_store] Cart options', [
                'product_id' => $cart->id,
                'product_name' => $cart->name,
                'size_id' => $sizeId,
                'product_size' => $sizeName,
                'color_id' => $colorId,
                'product_color' => $colorName,
                'options_raw' => $cart->options ? json_decode(json_encode($cart->options), true) : [],
            ]);

            if (!$sizeName && $sizeId) {
                $s = Size::find($sizeId);
                $sizeName = $s ? ($s->sizeName ?? $s->size_name ?? null) : null;
            }
            if (!$colorName && $colorId) {
                $c = Color::find($colorId);
                $colorName = $c ? ($c->getAttribute('colorName') ?? $c->getAttribute('color_name') ?? $c->colorName ?? null) : null;
            }

            $savedSize  = $sizeId ?: $sizeName;
            $savedColor = $colorId ?: $colorName;
            Log::channel('single')->info('[POS order_store] Saving to order_details', [
                'product_id' => $cart->id,
                'product_size' => $savedSize,
                'product_color' => $savedColor,
            ]);

            $order_details                   = new OrderDetails();
            $order_details->order_id         = $order->id;
            $order_details->product_id       = $cart->id;
            $order_details->product_name     = $cart->name;
            $order_details->purchase_price   = isset($cart->options->purchase_price) ? $cart->options->purchase_price : 0;
            $order_details->product_discount = isset($cart->options->product_discount) ? $cart->options->product_discount : 0;
            $order_details->sale_price       = $cart->price;
            $order_details->qty              = $cart->qty;
            $order_details->product_size     = $savedSize;
            $order_details->product_color    = $savedColor;
            $order_details->save();
        }

        // নতুন অর্ডার প্লেস করলে স্টক কমানো (oldStatus = 0, newStatus = 1)
        $order->setRelation('shipping', $shipping);
        $this->recordOrderHistory(
            $order,
            'created',
            'Order Created',
            'Order was created from admin panel.',
            $this->buildOrderHistorySnapshot($order, $shipping, $this->buildCartItemsHistorySummary()),
            (int) $order->order_status,
            Auth::guard('admin')->id() ?: auth()->id(),
            null,
            'admin'
        );

        $this->handleStockChange($order, 0, (int) $order->order_status);

        Cart::instance('pos_shopping')->destroy();
        Session::forget(['pos_shipping', 'pos_discount', 'pos_coupon_code', 'product_discount']);

        Toastr::success('Order created successfully', 'Success!');
        return redirect()->route('admin.order.create')->with('order_create_success', 'Order created successfully.');
    }

    public function cart_add(Request $request)
    {
        $product = Product::select('id', 'name', 'stock', 'new_price', 'old_price', 'purchase_price', 'slug')
            ->where(['id' => $request->id])->first();

        $qty      = 1;
        $cartinfo = Cart::instance('pos_shopping')->add([
            'id'      => $product->id,
            'name'    => $product->name,
            'qty'     => $qty,
            'price'   => $product->new_price,
            'options' => [
                'slug'            => $product->slug,
                'image'           => (isset($product->image) && isset($product->image->image)) ? $product->image->image : null,
                'old_price'       => $product->old_price,
                'purchase_price'  => $product->purchase_price,
                'product_discount'=> 0,
                'admin_price'     => $product->new_price,
                'product_size'    => null,
                'product_color'   => null,
                'size_id'         => null,
                'color_id'        => null,
            ],
        ]);
        return response()->json(compact('cartinfo'));
    }

    public function updateNote(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'note_type'=> 'required|in:admin',
            'selected_comment' => 'nullable|string|max:255',
            'custom_comment' => 'nullable|string',
        ]);

        $order = Order::findOrFail($request->order_id);
        $selectedComment = trim((string) $request->selected_comment);
        $customComment = trim((string) $request->custom_comment);

        if ($selectedComment === '' && $customComment === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select or write a comment.',
            ], 422);
        }

        $parts = array_values(array_filter([$selectedComment, $customComment], function ($value) {
            return trim((string) $value) !== '';
        }));
        $finalComment = trim(implode(' | ', $parts));
        $oldAdminNote = $this->sanitizeAdminNoteText((string) $order->admin_note);

        $order->admin_note = $this->preservePrintedMarker((string) $order->admin_note, $finalComment);
        $order->save();

        $adminId = Auth::guard('admin')->id() ?: auth()->id();

        $noteHistory = OrderAdminNote::create([
            'order_id' => $order->id,
            'admin_id' => $adminId,
            'selected_comment' => $selectedComment !== '' ? $selectedComment : null,
            'custom_comment' => $customComment !== '' ? $customComment : null,
            'final_comment' => $finalComment,
        ]);

        $this->recordOrderHistory(
            $order,
            'admin_note',
            'Admin Note Added',
            'Admin note was added or updated from order list.',
            [
                'Admin Note' => [
                    'old' => $oldAdminNote !== '' ? $oldAdminNote : 'N/A',
                    'new' => $finalComment,
                ],
            ],
            (int) $order->order_status,
            $adminId,
            null,
            'admin'
        );

        return response()->json([
            'status' => 'success',
            'note'   => $finalComment,
            'history' => [
                'id' => $noteHistory->id,
                'comment' => $noteHistory->final_comment,
                'comment_by' => optional($noteHistory->adminUser)->name ?: 'Admin',
                'created_at' => $noteHistory->created_at ? $noteHistory->created_at->format('d/m/Y g:i A') : '',
            ],
        ]);
    }

    public function adminNoteHistory(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $notes = OrderAdminNote::where('order_id', $order->id)
            ->with('adminUser:id,name')
            ->latest('id')
            ->get()
            ->map(function ($note, $index) {
                return [
                    'sl' => $index + 1,
                    'comment' => $note->final_comment,
                    'comment_by' => optional($note->adminUser)->name ?: 'Admin',
                    'created_at' => $note->created_at ? $note->created_at->format('d/m/Y g:i A') : '',
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'notes' => $notes,
            'presets' => $this->getAdminNotePresetOptions(),
            'latest_note' => $this->sanitizeAdminNoteText((string) $order->admin_note),
        ]);
    }

    public function cart_content(Request $request)
    {
        $cartinfo = Cart::instance('pos_shopping')->content();
        if ($request->get('mode') === 'pos') {
            return view('backEnd.order.cart_table_rows_pos', compact('cartinfo'));
        }
        return view('backEnd.order.cart_content', compact('cartinfo'));
    }

    public function cart_details()
    {
        $cartinfo = Cart::instance('pos_shopping')->content();
        return view('backEnd.order.cart_details', compact('cartinfo'));
    }

    public function cart_increment(Request $request)
    {
        $qty  = $request->qty + 1;
        $cart = Cart::instance('pos_shopping')->content()->where('rowId', $request->id)->first();

        $cartinfo = Cart::instance('pos_shopping')->update($request->id, [
            'qty'     => $qty,
            'options' => [
                'slug'            => $cart->options->slug,
                'image'           => $cart->options->image,
                'old_price'       => $cart->options->old_price,
                'purchase_price'  => $cart->options->purchase_price,
                'product_discount'=> $cart->options->product_discount ?? 0,
                'admin_price'     => $cart->options->admin_price ?? $cart->price,
                'product_size'    => $cart->options->product_size,
                'product_color'   => $cart->options->product_color,
                'size_id'         => $cart->options->size_id ?? null,
                'color_id'        => $cart->options->color_id ?? null,
            ],
        ]);
        return response()->json($cartinfo);
    }

    public function cart_decrement(Request $request)
    {
        $qty  = max(1, $request->qty - 1);
        $cart = Cart::instance('pos_shopping')->content()->where('rowId', $request->id)->first();

        $cartinfo = Cart::instance('pos_shopping')->update($request->id, [
            'qty'     => $qty,
            'options' => [
                'slug'            => $cart->options->slug,
                'image'           => $cart->options->image,
                'old_price'       => $cart->options->old_price,
                'purchase_price'  => $cart->options->purchase_price,
                'product_discount'=> $cart->options->product_discount ?? 0,
                'admin_price'     => $cart->options->admin_price ?? $cart->price,
                'product_size'    => $cart->options->product_size,
                'product_color'   => $cart->options->product_color,
                'size_id'         => $cart->options->size_id ?? null,
                'color_id'        => $cart->options->color_id ?? null,
            ],
        ]);

        return response()->json($cartinfo);
    }

    public function cart_set_qty(Request $request)
    {
        $cart = Cart::instance('pos_shopping')->content()->where('rowId', $request->id)->first();
        if (!$cart) {
            return response()->json(['error' => 'Cart item not found'], 404);
        }

        $qty = max(1, (int) $request->qty);

        $cartinfo = Cart::instance('pos_shopping')->update($request->id, [
            'qty'     => $qty,
            'options' => [
                'slug'              => $cart->options->slug,
                'image'             => $cart->options->image,
                'old_price'         => $cart->options->old_price,
                'purchase_price'    => $cart->options->purchase_price,
                'product_discount'  => $cart->options->product_discount ?? 0,
                'admin_price'       => $cart->options->admin_price ?? $cart->price,
                'product_size'      => $cart->options->product_size,
                'product_color'     => $cart->options->product_color,
                'size_id'           => $cart->options->size_id ?? null,
                'color_id'          => $cart->options->color_id ?? null,
                'details_id'        => $cart->options->details_id ?? null,
                'product_color_name'=> $cart->options->product_color_name ?? null,
                'product_size_name' => $cart->options->product_size_name ?? null,
            ],
        ]);

        Session::put('product_discount', Cart::instance('pos_shopping')->content()->sum(function ($item) {
            return ((float) ($item->options->product_discount ?? 0)) * ((float) $item->qty);
        }));

        return response()->json($cartinfo);
    }

    public function cart_remove(Request $request)
    {
        Cart::instance('pos_shopping')->remove($request->id);
        $cartinfo = Cart::instance('pos_shopping')->content();
        return response()->json($cartinfo);
    }

    public function product_discount(Request $request)
    {
        $cart = Cart::instance('pos_shopping')->content()->where('rowId', $request->id)->first();
        $discount = max(0, (float) $request->discount);
        $discount = min($discount, (float) $cart->price);

        $cartinfo = Cart::instance('pos_shopping')->update($request->id, [
            'options' => [
                'slug'            => $cart->options->slug,
                'image'           => $cart->options->image,
                'old_price'       => $cart->options->old_price,
                'purchase_price'  => $cart->options->purchase_price,
                'product_discount'=> $discount,
                'admin_price'     => $cart->options->admin_price ?? $cart->price,
                'product_size'    => $cart->options->product_size,
                'product_color'   => $cart->options->product_color,
                'size_id'         => $cart->options->size_id ?? null,
                'color_id'        => $cart->options->color_id ?? null,
            ],
        ]);

        Session::put('product_discount', Cart::instance('pos_shopping')->content()->sum(function ($item) {
            return ((float) ($item->options->product_discount ?? 0)) * ((float) $item->qty);
        }));

        return response()->json($cartinfo);
    }

    public function cart_sell_price(Request $request)
    {
        $cart = Cart::instance('pos_shopping')->content()->where('rowId', $request->id)->first();
        if (!$cart) {
            return response()->json(['error' => 'Cart item not found'], 404);
        }

        $sellPrice = max(0, (float) $request->price);
        $currentDiscount = (float) ($cart->options->product_discount ?? 0);
        if ($currentDiscount > $sellPrice) {
            $currentDiscount = $sellPrice;
        }

        $cartinfo = Cart::instance('pos_shopping')->update($request->id, [
            'price' => $sellPrice,
            'options' => [
                'slug'             => $cart->options->slug,
                'image'            => $cart->options->image,
                'old_price'        => $cart->options->old_price,
                'purchase_price'   => $cart->options->purchase_price,
                'product_discount' => $currentDiscount,
                'product_size'     => $cart->options->product_size,
                'product_color'    => $cart->options->product_color,
                'size_id'          => $cart->options->size_id ?? null,
                'color_id'         => $cart->options->color_id ?? null,
                'details_id'       => $cart->options->details_id ?? null,
                'product_color_name' => $cart->options->product_color_name ?? null,
                'product_size_name'  => $cart->options->product_size_name ?? null,
                'admin_price'      => (float) ($cart->options->admin_price ?? $cart->price),
            ],
        ]);

        Session::put('product_discount', Cart::instance('pos_shopping')->content()->sum(function ($item) {
            return ((float) ($item->options->product_discount ?? 0)) * ((float) $item->qty);
        }));

        return response()->json($cartinfo);
    }

    public function cart_admin_price(Request $request)
    {
        $cart = Cart::instance('pos_shopping')->content()->where('rowId', $request->id)->first();
        if (!$cart) {
            return response()->json(['error' => 'Cart item not found'], 404);
        }

        $adminPrice = max(0, (float) $request->price);

        $cartinfo = Cart::instance('pos_shopping')->update($request->id, [
            'options' => [
                'slug'             => $cart->options->slug,
                'image'            => $cart->options->image,
                'old_price'        => $cart->options->old_price,
                'purchase_price'   => $cart->options->purchase_price,
                'product_discount' => $cart->options->product_discount ?? 0,
                'product_size'     => $cart->options->product_size,
                'product_color'    => $cart->options->product_color,
                'size_id'          => $cart->options->size_id ?? null,
                'color_id'         => $cart->options->color_id ?? null,
                'details_id'       => $cart->options->details_id ?? null,
                'product_color_name' => $cart->options->product_color_name ?? null,
                'product_size_name'  => $cart->options->product_size_name ?? null,
                'admin_price'      => $adminPrice,
            ],
        ]);

        return response()->json($cartinfo);
    }

    public function cart_update(Request $request)
    {
        Log::channel('single')->info('[POS cart_update] Request', [
            'id' => $request->id,
            'size_id' => $request->size_id,
            'color_id' => $request->color_id,
            'all' => $request->all(),
        ]);

        $rowId = $request->id;
        $cartItem = Cart::instance('pos_shopping')->content()->where('rowId', $rowId)->first();

        // rowId দিয়ে না পেলে product_id দিয়ে খুঁজুন (update এর পর rowId বদলে যেতে পারে)
        if (!$cartItem && $request->product_id) {
            $cartItem = Cart::instance('pos_shopping')->content()->firstWhere('id', $request->product_id);
            if ($cartItem) {
                $rowId = $cartItem->rowId;
            }
        }

        if (!$cartItem) {
            Log::channel('single')->warning('[POS cart_update] Cart item not found', ['rowId' => $rowId, 'product_id' => $request->product_id]);
            return response()->json(['error' => 'Cart item not found']);
        }

        $sizeId  = $request->size_id ?: ($request->product_size ?: null);
        $colorId = $request->color_id ?: ($request->product_color ?: null);

        $product = Product::find($cartItem->id);
        $newPrice = $cartItem->price;
        $sizeName = null;
        $colorName = null;

        if ($product) {
            $variant = ProductVariantPrice::where('product_id', $product->id)
                ->when($sizeId, fn($q) => $q->where('size_id', $sizeId))
                ->when($colorId, fn($q) => $q->where('color_id', $colorId))
                ->first();

            if ($variant && $variant->price > 0) {
                $newPrice = $variant->price;
            } else {
                $newPrice = $product->new_price ?? $product->old_price ?? $cartItem->price;
            }

            if ($sizeId) {
                $size = Size::find($sizeId);
                $sizeName = $size ? ($size->sizeName ?? $size->size_name ?? null) : null;
            }
            if ($colorId) {
                $color = Color::find($colorId);
                $colorName = $color ? ($color->getAttribute('colorName') ?? $color->getAttribute('color_name') ?? $color->colorName ?? null) : null;
            }
        }

        $options = [
            'product_size'    => $sizeName ?? $cartItem->options->product_size,
            'product_color'   => $colorName ?? $cartItem->options->product_color,
            'size_id'         => $sizeId,
            'color_id'        => $colorId,
            'slug'            => $cartItem->options->slug,
            'image'           => $cartItem->options->image,
            'old_price'       => $cartItem->options->old_price,
            'purchase_price'  => $cartItem->options->purchase_price,
            'product_discount'=> $cartItem->options->product_discount ?? 0,
            'admin_price'     => $cartItem->options->admin_price ?? $cartItem->price,
        ];
        $updatedItem = Cart::instance('pos_shopping')->update($rowId, ['price' => $newPrice, 'options' => $options]);

        Log::channel('single')->info('[POS cart_update] Saved', [
            'rowId' => $updatedItem ? $updatedItem->rowId : $rowId,
            'sizeId' => $sizeId,
            'colorId' => $colorId,
            'sizeName' => $sizeName,
            'colorName' => $colorName,
        ]);

        // update() options বদলালে rowId বদলে যায়, তাই Cart::get($rowId) ব্যর্থ হয়; update এর রিটার্ন ব্যবহার করুন
        return response()->json($updatedItem ?? Cart::instance('pos_shopping')->content()->firstWhere('id', $cartItem->id));
    }

    public function cart_shipping(Request $request)
    {
        if ($request->filled('amount_manual')) {
            $shipping = max(0, (float) $request->amount_manual);
            Session::put('pos_shipping', $shipping);
            return response()->json($shipping);
        }

        $shippingcharge = ShippingCharge::where(['status' => 1, 'id' => $request->id])->first();
        $shipping = ($shippingcharge && isset($shippingcharge->amount)) ? $shippingcharge->amount : 0;

        Session::put('pos_shipping', $shipping);
        return response()->json($shipping);
    }

    public function posApplyCoupon(Request $request)
    {
        $request->validate(['coupon_code' => 'required']);
        $code = trim($request->coupon_code);

        $coupon = Coupon::where('code', $code)->where('status', 1)->first();
        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'কুপন কোড বৈধ নয়']);
        }

        $today = Carbon::now()->format('Y-m-d');
        if (($coupon->valid_from && $today < $coupon->valid_from) || ($coupon->valid_to && $today > $coupon->valid_to)) {
            return response()->json(['success' => false, 'message' => 'কুপন মেয়াদ শেষ অথবা এখনো চালু হয়নি']);
        }

        $subtotalRaw = Cart::instance('pos_shopping')->subtotal();
        $subtotal = (float) preg_replace('/[^\d.]/', '', (string) $subtotalRaw);
        if ($subtotal <= 0) {
            return response()->json(['success' => false, 'message' => 'কার্টে প্রোডাক্ট যোগ করুন']);
        }

        $minPurchase = (float) ($coupon->min_purchase ?? 0);
        if ($minPurchase > 0 && $subtotal < $minPurchase) {
            return response()->json(['success' => false, 'message' => "ন্যূনতম ক্রয় ৳{$minPurchase} প্রয়োজন"]);
        }

        $type = strtolower((string) ($coupon->type ?? 'flat'));
        $value = (float) ($coupon->value ?? 0);
        if ($type === 'percent' || $type === 'percentage') {
            $discount = $subtotal * ($value / 100);
        } else {
            $discount = $value;
        }
        $discount = round(min($discount, $subtotal), 2);
        Session::put('pos_coupon_code', $coupon->code);
        Session::put('pos_discount', $discount);

        return response()->json([
            'success' => true,
            'message' => 'কুপন অ্যাপ্লাই হয়েছে! বাঁচালেন ৳' . $discount,
        ]);
    }

    public function posRemoveCoupon()
    {
        Session::forget(['pos_coupon_code', 'pos_discount']);
        return response()->json(['success' => true]);
    }

    public function cart_clear(Request $request)
    {
        Cart::instance('pos_shopping')->destroy();
        Session::forget(['pos_shipping', 'pos_discount', 'pos_coupon_code']);
        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER EDIT / UPDATE (POS)
    |--------------------------------------------------------------------------
    */

    public function order_edit($invoice_id)
    {
        // ✅ Limit products for POS dropdown to avoid memory issues
        $products = Product::select('id', 'name', 'new_price', 'product_code')
            ->where(['status' => 1])
            ->limit(100)
            ->get();

        $shippingcharge = ShippingCharge::where('status', 1)->get();
        $order          = Order::where('invoice_id', $invoice_id)->firstOrFail();

        Cart::instance('pos_shopping')->destroy();

        $shippinginfo = Shipping::where('order_id', $order->id)->first();
        Session::put('product_discount', $order->discount);
        Session::put('pos_shipping', $order->shipping_charge);

        $orderdetails = OrderDetails::where('order_id', $order->id)
            ->with(['image', 'color', 'size'])
            ->get();

        $isResellerOrder = !is_null($order->customer_payable_amount) || ((float) $order->reseller_profit > 0);
        $customerProductBase = max(0, (float) (($order->customer_payable_amount ?? $order->amount) - $order->shipping_charge));
        $totalAdminBase = (float) $orderdetails->sum(function ($detail) {
            return ((float) $detail->sale_price) * ((float) $detail->qty);
        });
        $remainingCustomerBase = $customerProductBase;
        $detailCount = $orderdetails->count();
        $detailIndex = 0;

        foreach ($orderdetails as $ordetails) {
            $detailIndex++;
            $adminUnitPrice = (float) $ordetails->sale_price;
            $customUnitPrice = $adminUnitPrice;

            if ($isResellerOrder) {
                $lineAdminTotal = $adminUnitPrice * (float) $ordetails->qty;

                if ($detailIndex === $detailCount) {
                    $lineCustomerTotal = $remainingCustomerBase;
                } elseif ($totalAdminBase > 0) {
                    $lineCustomerTotal = round(($lineAdminTotal / $totalAdminBase) * $customerProductBase, 2);
                } else {
                    $lineCustomerTotal = round($customerProductBase / max(1, $detailCount), 2);
                }

                $remainingCustomerBase -= $lineCustomerTotal;
                $customUnitPrice = $ordetails->qty > 0 ? round($lineCustomerTotal / (float) $ordetails->qty, 2) : 0;
            }

            Cart::instance('pos_shopping')->add([
                'id'      => $ordetails->product_id,
                'name'    => $ordetails->product_name,
                'qty'     => $ordetails->qty,
                'price'   => $customUnitPrice,
                'options' => [
                    'image'             => (isset($ordetails->image) && isset($ordetails->image->image) ? $ordetails->image->image : 'public/no-image.png'),
                    'purchase_price'    => $ordetails->purchase_price,
                    'product_discount'  => $ordetails->product_discount,
                    'details_id'        => $ordetails->id,
                    'product_color'     => $ordetails->product_color,
                    'product_size'      => $ordetails->product_size,
                    'product_color_name'=> isset($ordetails->color->name) ? $ordetails->color->name : (isset($ordetails->product_color) ? $ordetails->product_color : 'N/A'),
                    'product_size_name' => isset($ordetails->size->name) ? $ordetails->size->name : (isset($ordetails->product_size) ? $ordetails->product_size : 'N/A'),
                    'admin_price'       => $ordetails->sale_price,
                ],
            ]);
        }

        $cartinfo = Cart::instance('pos_shopping')->content();
        $quickOrderStatuses = OrderStatus::query()
            ->whereIn('slug', ['new', 'pending', 'processing', 'paid-return'])
            ->orWhereIn('name', ['New', 'Pending', 'Processing', 'Approved', 'Paid Return'])
            ->orderByRaw("CASE
                WHEN LOWER(slug) = 'new' OR LOWER(name) = 'new' THEN 1
                WHEN LOWER(slug) = 'pending' OR LOWER(name) = 'pending' THEN 2
                WHEN LOWER(slug) = 'processing' OR LOWER(name) IN ('processing', 'approved') THEN 3
                WHEN LOWER(slug) IN ('paid-return', 'paid_return') OR LOWER(name) = 'paid return' THEN 4
                ELSE 5 END")
            ->get();

        return view('backEnd.order.edit', compact(
            'products',
            'cartinfo',
            'shippingcharge',
            'shippinginfo',
            'order',
            'quickOrderStatuses'
        ));
    }

    public function order_update(Request $request)
    {
        $this->validate($request, [
            'name'    => 'required',
            'phone'   => 'required',
            'address' => 'required',
            'area'    => 'required',
            'order_status' => 'nullable|exists:order_statuses,id',
            'paid_return_amount' => 'nullable|numeric|min:0',
        ]);

        if (Cart::instance('pos_shopping')->count() <= 0) {
            Toastr::error('Your shopping cart is empty', 'Failed!');
            return redirect()->back();
        }

        $subtotalRaw = Cart::instance('pos_shopping')->subtotal();
        $subtotal    = (float) preg_replace('/[^\d.]/', '', (string) $subtotalRaw);
        $discount    = (float) Session::get('pos_discount', 0) + (float) Session::get('product_discount', 0);
        $adminSubtotal = (float) Cart::instance('pos_shopping')->content()->sum(function ($item) {
            return ((float) ($item->options->admin_price ?? $item->price)) * ((float) $item->qty);
        });
        $shippingfee = ShippingCharge::find($request->area);

        $customer = Customer::firstOrCreate(
            ['phone' => $request->phone],
            [
                'name'     => $request->name,
                'slug'     => $request->name,
                'password' => bcrypt(rand(111111, 999999)),
                'verify'   => 1,
                'status'   => 'active'
            ]
        );

        $order = Order::findOrFail($request->order_id);
        $shippingBefore = Shipping::where('order_id', $order->id)->first();
        $beforeSnapshot = $this->buildOrderHistorySnapshot($order, $shippingBefore);
        $orderSource = trim((string) $request->order_source);
        $courierNote = trim((string) $request->courier_note);
        $originalOrderStatus = (int) $order->order_status;
        $selectedOrderStatus = (int) ($request->order_status ?: $originalOrderStatus);
        $newShippingCharge = $request->filled('shipping_charge')
            ? max(0, (float) $request->shipping_charge)
            : (isset($shippingfee->amount) ? (float) $shippingfee->amount : 0);
        $customerProductAmount = max(0, $subtotal - $discount);
        $calculatedAmount  = $customerProductAmount + $newShippingCharge;
        $isResellerOrder   = !is_null($order->customer_payable_amount) || ((float) $order->reseller_profit > 0);

        if ($isResellerOrder) {
            $order->amount = $calculatedAmount;
            $order->customer_payable_amount = $calculatedAmount;
            $order->reseller_profit = round($customerProductAmount - $adminSubtotal, 2);
        } else {
            $order->amount = $calculatedAmount;
        }

        $order->discount        = $discount;
        $order->shipping_charge = $newShippingCharge;
        $order->customer_id     = $customer->id;
        $order->order_status    = $selectedOrderStatus;
        if (Schema::hasColumn('orders', 'paid_return_amount')) {
            $order->paid_return_amount = max(0, (float) $request->input('paid_return_amount', 0));
        }
        $order->note            = $orderSource !== '' ? $orderSource : null;
        if (Schema::hasColumn('orders', 'order_source_channel') && $order->created_by) {
            $order->order_source_channel = 'created';
        }
        if (Schema::hasColumn('orders', 'order_note')) {
            $order->order_note = $courierNote !== '' ? $courierNote : null;
        }
        $order->save();

        $shipping           = Shipping::where('order_id', $order->id)->firstOrFail();
        $shipping->name     = $request->name;
        $shipping->phone    = $request->phone;
        $shipping->address  = $request->address;
        $shipping->area     = isset($shippingfee->name) ? $shippingfee->name : $shipping->area;
        $shipping->save();

        Session::put('pos_shipping', $newShippingCharge);

        $payment                 = Payment::where('order_id', $order->id)->firstOrNew(['order_id' => $order->id]);
        $payment->customer_id    = $customer->id;
        $payment->payment_method = 'Cash On Delivery';
        $payment->amount         = $order->amount;
        $payment->payment_status = 'pending';
        $payment->save();

        $existingDetails = OrderDetails::where('order_id', $order->id)->pluck('id')->toArray();
        $updatedIds      = [];

        foreach (Cart::instance('pos_shopping')->content() as $cart) {
            if (!empty($cart->options->details_id) && in_array($cart->options->details_id, $existingDetails)) {
                $detail = OrderDetails::find($cart->options->details_id);
            } else {
                $detail              = new OrderDetails();
                $detail->order_id    = $order->id;
                $detail->product_id  = $cart->id;
                $detail->product_name= $cart->name;
            }

            $detail->purchase_price   = isset($cart->options->purchase_price) ? $cart->options->purchase_price : 0;
            $detail->product_discount = isset($cart->options->product_discount) ? $cart->options->product_discount : 0;
            $detail->product_color    = isset($cart->options->product_color) ? $cart->options->product_color : null;
            $detail->product_size     = isset($cart->options->product_size) ? $cart->options->product_size : null;
            $detail->sale_price       = (float) ($cart->options->admin_price ?? $cart->price);
            $detail->qty              = $cart->qty;
            $detail->save();

            $updatedIds[] = $detail->id;
        }

        OrderDetails::where('order_id', $order->id)
            ->whereNotIn('id', $updatedIds)
            ->delete();

        $order->setRelation('shipping', $shipping);
        $afterSnapshot = $this->buildOrderHistorySnapshot($order, $shipping, $this->buildCartItemsHistorySummary());
        $changes = $this->buildOrderHistoryChanges($beforeSnapshot, $afterSnapshot);

        if (!empty($changes)) {
            $this->recordOrderHistory(
                $order,
                'updated',
                'Order Updated',
                'Order was updated from edit page.',
                $changes,
                (int) $order->order_status,
                Auth::guard('admin')->id() ?: auth()->id(),
                null,
                'admin'
            );
        }

        Cart::instance('pos_shopping')->destroy();
        Session::forget(['pos_shipping', 'pos_discount', 'product_discount']);

        Toastr::success('Order updated successfully!', 'Success!');
        return redirect()->route('admin.orders', 'pending');
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS UPDATE
    |--------------------------------------------------------------------------
    */

/*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS UPDATE (With Digital Product Generation)
    |--------------------------------------------------------------------------
    */
    public function updatePaymentStatus(Request $request)
    {
        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Order not found!',
            ]);
        }

        // ১. অর্ডার টেবিলে স্ট্যাটাস আপডেট
        $oldPaymentStatus = trim((string) ($order->payment_status ?? ''));
        $order->payment_status = $request->payment_status;
        $order->save();

        // ২. পেমেন্ট টেবিলে স্ট্যাটাস আপডেট
        $payment = Payment::where('order_id', $order->id)->first();
        if ($payment) {
            $payment->payment_status = $request->payment_status;
            $payment->save();
        }

        // ==============================================================
        // ⭐ NEW LOGIC: জেনারেট ডিজিটাল ডাউনলোড (যদি পেইড হয়)
        // ==============================================================
        $paid_keywords = ['paid', 'completed', 'success', 'approved'];

        if (in_array(strtolower($request->payment_status), $paid_keywords)) {
            
            $orderDetails = OrderDetails::where('order_id', $order->id)
                ->with('product:id,is_digital,digital_file,download_limit,download_expire_days')
                ->get();

            foreach ($orderDetails as $detail) {
                $product = $detail->product;

                if ($product) {
                    // চেক করি: এই প্রোডাক্টের জন্য ইতিমধ্যে ডাউনলোড লিংক আছে কিনা?
                    $alreadyExists = \App\Models\DigitalDownload::where('order_id', $order->id)
                                    ->where('product_id', $product->id)
                                    ->exists();

                    // যদি লিংক না থাকে এবং প্রোডাক্টটি ডিজিটাল হয় (আপনার লজিক অনুযায়ী চেক বসাতে পারেন)
                    // আমি এখানে ধরে নিচ্ছি আপনি সব প্রোডাক্টের জন্যই জেনারেট করতে চান, অথবা 
                    // যদি আপনার প্রোডাক্ট টেবিলে 'type' == 'digital' থাকে তবে সেই কন্ডিশনও দিতে পারেন।
                    
                    if (!$alreadyExists) {
                         // নতুন ডাউনলোড লিংক তৈরি করা হচ্ছে
                         \App\Models\DigitalDownload::create([
                            'order_id'    => $order->id,
                            'customer_id' => $order->customer_id,
                            'product_id'  => $product->id,
                            'token'       => \Illuminate\Support\Str::random(64), // ইউনিক টোকেন
                            'file_path'   => isset($product->digital_file) ? $product->digital_file : 'default_file', // ফাইলের নাম বা পাথ
                            'remaining_downloads' => 9999, // আনলিমিটেড বা নির্দিষ্ট সংখ্যা
                            'expires_at'  => null,
                        ]);
                    }
                }
            }
        }
        // ==============================================================

        if ($oldPaymentStatus !== trim((string) $request->payment_status)) {
            $this->recordOrderHistory(
                $order,
                'payment_status',
                'Payment Status Updated',
                'Payment status was updated from invoice view.',
                [
                    'Payment Status' => [
                        'old' => $oldPaymentStatus !== '' ? ucfirst($oldPaymentStatus) : 'N/A',
                        'new' => ucfirst((string) $request->payment_status),
                    ],
                ],
                (int) $order->order_status,
                Auth::guard('admin')->id() ?: auth()->id(),
                null,
                'admin'
            );
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment status updated & Digital assets generated successfully!',
        ]);
    }

    /**
     * Distribute vendor earnings and admin commission for completed orders.
     */
    private function distributeVendorEarnings(Order $order): void
    {
        $details = $order->orderdetails()
            ->with([
                'product:id,vendor_id,name',
                'product.vendor:id,commission_rate'
            ])
            ->get();

        foreach ($details as $item) {
            $product = $item->product;
            if (!$product || !$product->vendor_id) {
                continue;
            }

            // Skip if already processed
            if ($item->vendor_paid_at) {
                continue;
            }

            $vendorId = $product->vendor_id;
            $vendor   = $product->vendor;

            // Vendor must be loaded; if missing skip to avoid extra query/N+1
            if (!$vendor) {
                \Log::warning('Vendor not loaded for product: ' . $product->id);
                continue;
            }

            $commissionRate = isset($vendor->commission_rate) ? $vendor->commission_rate : config('app.vendor_commission', 10);
            $lineTotal      = (float) (isset($item->sale_price) ? $item->sale_price : 0) * (float) (isset($item->qty) ? $item->qty : 0);

            $adminCommission = round($lineTotal * ($commissionRate / 100), 2);
            $vendorEarning   = max(0, round($lineTotal - $adminCommission, 2));

            // Update order detail record
            $item->update([
                'vendor_id'        => $vendorId,
                'commission_rate'  => $commissionRate,
                'admin_commission' => $adminCommission,
                'vendor_earning'   => $vendorEarning,
                'vendor_paid_at'   => now(),
            ]);

            // Update wallet
            $wallet = VendorWallet::firstOrCreate(['vendor_id' => $vendorId]);
            $wallet->balance       += $vendorEarning;
            $wallet->total_earned  += $vendorEarning;
            $wallet->save();

            VendorWalletTransaction::create([
                'vendor_id'   => $vendorId,
                'type'        => 'earning',
                'status'      => 'completed',
                'amount'      => $vendorEarning,
                'source_type' => 'order',
                'source_id'   => $item->id,
                'note'        => 'Order #' . $order->invoice_id . ' item earning',
            ]);

            // Add admin commission to fund transaction
            if ($adminCommission > 0) {
                \App\Models\FundTransaction::create([
                    'direction'  => 'in',
                    'source'     => 'vendor_commission',
                    'source_id'  => $order->id,
                    'amount'     => $adminCommission,
                    'note'       => 'Vendor commission from Order #' . $order->invoice_id . ' - Product: ' . $item->product_name,
                    'created_by' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * Credit reseller wallet when order is delivered.
     * Only credits if order has reseller_profit and hasn't been credited before.
     */
    private function creditResellerWallet(Order $order): void
    {
        // Check if this is a reseller order
        if (!$order->reseller_profit || $order->reseller_profit <= 0) {
            return;
        }

        // Get reseller user from order
        // First check user_id (if reseller placed order directly)
        $resellerUser = null;
        if ($order->user_id) {
            $resellerUser = User::find($order->user_id);
            // Verify it's a reseller
            if ($resellerUser && 
                ($resellerUser->hasRole('reseller') || 
                 (isset($resellerUser->role) && strtolower($resellerUser->role) === 'reseller'))) {
                // Reseller found via user_id
            } else {
                $resellerUser = null;
            }
        }

        // Fallback: Check customer email (for old orders)
        if (!$resellerUser && $order->customer && $order->customer->email) {
            $resellerUser = User::where('email', $order->customer->email)
                ->where(function($query) {
                    $query->where('role', 'reseller')
                          ->orWhereHas('roles', function($q) {
                              $q->where('name', 'reseller');
                          });
                })
                ->first();
        }

        if (!$resellerUser) {
            return;
        }

        // Check if already credited (to avoid double credit)
        if ($order->reseller_wallet_credited) {
            return;
        }

        $resellerProfit = (float) $order->reseller_profit;
        
        if ($resellerProfit > 0) {
            // Update reseller wallet balance
            $resellerUser->wallet_balance = (isset($resellerUser->wallet_balance) ? $resellerUser->wallet_balance : 0) + $resellerProfit;
            $resellerUser->save();

            // Mark order as credited to avoid double credit
            $order->reseller_wallet_credited = true;
            $order->save();

            // Optional: Log the transaction (if you have a reseller wallet transaction table)
            // You can create a similar table like VendorWalletTransaction for resellers
        }
    }
}
