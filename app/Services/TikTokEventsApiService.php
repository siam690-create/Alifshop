<?php

namespace App\Services;

use App\Models\FacebookCapiSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokEventsApiService
{
    protected $accessToken;
    protected $pixelId;
    protected $testEventCode;
    protected $setting;
    protected $initialized = false;

    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $this->setting = Cache::remember('tiktok_events_api_settings', 3600, function () {
                return FacebookCapiSetting::query()
                    ->where('tiktok_status', 1)
                    ->whereNotNull('tiktok_pixel_id')
                    ->whereNotNull('tiktok_access_token')
                    ->first();
            });
        } catch (\Throwable $e) {
            $this->setting = null;
        }

        if ($this->setting) {
            $this->pixelId = $this->setting->tiktok_pixel_id;
            $this->accessToken = $this->setting->tiktok_access_token;
            $this->testEventCode = $this->setting->tiktok_test_event_code;
        }

        $this->initialized = true;
    }

    public function shouldSendPurchaseForContext(string $context): bool
    {
        $this->initialize();

        return ($this->setting->purchase_trigger ?? 'order_created') === $context;
    }

    public function shouldSendPurchaseForStatus($status): bool
    {
        $this->initialize();

        $trigger = $this->setting->purchase_trigger ?? 'order_created';
        $statusText = strtolower(trim((string) $status));
        $aliases = [
            'order_confirmed' => ['pending', 'processing', 'confirmed', 'confirm', 'accepted'],
            'shipped' => ['shipped', 'ship', 'in courier', 'on the way', 'courier'],
            'delivered' => ['delivered', 'completed', 'complete'],
        ];

        return in_array($statusText, $aliases[$trigger] ?? [], true);
    }

    public function sendPurchaseForOrder(Order $order, string $context = 'order_created', $amount = null, ?string $sourceUrl = null)
    {
        $this->initialize();

        if (!$this->pixelId || !$this->accessToken || !$this->shouldSendPurchaseForContext($context)) {
            return false;
        }

        return $this->dispatchPurchaseForOrder($order, $amount, $sourceUrl, $context);
    }

    public function sendPurchaseForOrderStatus(Order $order, $status, $amount = null, ?string $sourceUrl = null)
    {
        $this->initialize();

        if (!$this->pixelId || !$this->accessToken || !$this->shouldSendPurchaseForStatus($status)) {
            return false;
        }

        return $this->dispatchPurchaseForOrder($order, $amount, $sourceUrl, $this->setting->purchase_trigger ?? 'order_created');
    }

    public function sendPurchaseFromPayload(array $data, array $userData = [], array $options = [])
    {
        $this->initialize();

        $context = $options['purchase_trigger_context'] ?? 'order_created';
        if (!$this->pixelId || !$this->accessToken || !$this->shouldSendPurchaseForContext($context)) {
            return false;
        }

        $eventId = $options['event_id'] ?? $data['event_id'] ?? null;
        if ($eventId && Cache::has('tiktok_events_purchase_sent_' . $eventId)) {
            return false;
        }

        $result = $this->sendEvent('CompletePayment', [
            'currency' => $data['currency'] ?? 'BDT',
            'value' => (float) ($data['value'] ?? 0),
            'order_id' => (string) ($data['order_id'] ?? ''),
            'content_ids' => $data['content_ids'] ?? [],
            'contents' => $data['contents'] ?? [],
        ], $userData, $options);

        if ($eventId && is_array($result) && ($result['success'] ?? false)) {
            Cache::put('tiktok_events_purchase_sent_' . $eventId, true, now()->addDays(45));
        }

        return $result;
    }

    protected function dispatchPurchaseForOrder(Order $order, $amount = null, ?string $sourceUrl = null, string $context = 'order_created')
    {
        $cacheKey = 'tiktok_events_purchase_sent_purchase_' . $order->id;
        if (Cache::has($cacheKey)) {
            return false;
        }

        $order->loadMissing(['customer', 'shipping', 'orderdetails']);

        $shipping = $order->shipping;
        $customer = $order->customer;
        $userData = [
            'email' => $customer->email ?? null,
            'phone' => $shipping->phone ?? $customer->phone ?? null,
            'name' => $shipping->name ?? $customer->name ?? null,
        ];

        $contents = $order->orderdetails->map(function ($detail) {
            return [
                'content_id' => (string) ($detail->product_id ?? $detail->id),
                'content_type' => 'product',
                'quantity' => (int) ($detail->qty ?? 1),
                'price' => (float) ($detail->sale_price ?? 0),
            ];
        })->values()->all();

        $result = $this->sendEvent('CompletePayment', [
            'currency' => 'BDT',
            'value' => (float) ($amount ?? $order->customer_payable_amount ?? $order->amount ?? 0),
            'order_id' => (string) ($order->invoice_id ?? $order->id),
            'content_ids' => collect($contents)->pluck('content_id')->all(),
            'contents' => $contents,
        ], $userData, [
            'event_id' => 'purchase_' . $order->id,
            'event_source_url' => $sourceUrl ?: request()->fullUrl(),
            'purchase_trigger_context' => $context,
        ]);

        if (is_array($result) && ($result['success'] ?? false)) {
            Cache::put($cacheKey, true, now()->addDays(45));
        }

        return $result;
    }

    public function sendEvent(string $eventName, array $data = [], array $userData = [], array $options = [])
    {
        $this->initialize();

        if (!$this->pixelId || !$this->accessToken) {
            return false;
        }

        $payload = [
            'event_source' => 'web',
            'event_source_id' => $this->pixelId,
            'data' => [[
                'event' => $eventName,
                'event_time' => $options['event_time'] ?? time(),
                'event_id' => $options['event_id'] ?? $data['event_id'] ?? uniqid(strtolower($eventName) . '_', true),
                'page' => [
                    'url' => $options['event_source_url'] ?? request()->fullUrl(),
                    'referrer' => request()->headers->get('referer'),
                ],
                'user' => $this->prepareUserData($userData),
                'properties' => $this->prepareProperties($data),
            ]],
        ];

        if ($this->testEventCode) {
            $payload['test_event_code'] = $this->testEventCode;
        }

        try {
            $response = Http::withHeaders([
                'Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->connectTimeout(1)->timeout(2)->post('https://business-api.tiktok.com/open_api/v1.3/event/track/', $payload);

            if ($response->successful()) {
                Log::info('TikTok Events API: Event sent successfully', [
                    'event_name' => $eventName,
                    'pixel_id' => $this->pixelId,
                    'response' => $response->json(),
                ]);

                return ['success' => true, 'response' => $response->json()];
            }

            Log::error('TikTok Events API: API request failed', [
                'event_name' => $eventName,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return ['success' => false, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('TikTok Events API: Request failed non-blocking', [
                'event_name' => $eventName,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function prepareUserData(array $userData): array
    {
        $prepared = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (!empty($userData['email'])) {
            $prepared['email'] = hash('sha256', strtolower(trim($userData['email'])));
        }

        if (!empty($userData['phone'])) {
            $phone = preg_replace('/\D+/', '', $userData['phone']);
            if (strlen($phone) === 11 && str_starts_with($phone, '01')) {
                $phone = '88' . $phone;
            }
            $prepared['phone'] = hash('sha256', $phone);
        }

        if (!empty($userData['name'])) {
            $prepared['external_id'] = hash('sha256', strtolower(trim($userData['name'])));
        }

        if (isset($_COOKIE['_ttp'])) {
            $prepared['ttp'] = $_COOKIE['_ttp'];
        }

        if (request()->query('ttclid')) {
            $prepared['ttclid'] = request()->query('ttclid');
        }

        return array_filter($prepared);
    }

    protected function prepareProperties(array $data): array
    {
        return array_filter([
            'currency' => strtoupper($data['currency'] ?? 'BDT'),
            'value' => isset($data['value']) ? (float) $data['value'] : null,
            'order_id' => isset($data['order_id']) ? (string) $data['order_id'] : null,
            'content_ids' => $data['content_ids'] ?? null,
            'contents' => $data['contents'] ?? null,
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }
}
