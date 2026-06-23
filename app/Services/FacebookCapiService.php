<?php

namespace App\Services;

use App\Models\FacebookCapiSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FacebookCapiService
{
    protected $accessToken;
    protected $pixelId;
    protected $testEventCode;
    protected $setting;
    protected $initialized = false;

    /**
     * Lazy load credentials - only when needed
     */
    protected function initialize()
    {
        if ($this->initialized) {
            return;
        }

        // Try to load from database first (with cache)
        $dbSetting = null;
        try {
            $dbSetting = Cache::remember('facebook_capi_settings', 3600, function () {
                return FacebookCapiSetting::where('status', 1)->first();
            });
        } catch (\Throwable $e) {
            // If migration not run yet or table missing, silently ignore and fallback to env/config
        }

        if ($dbSetting) {
            $this->setting = $dbSetting;
            $this->accessToken = $dbSetting->access_token;
            $this->pixelId = $dbSetting->pixel_id;
            $this->testEventCode = $dbSetting->test_event_code;
        } else {
            $this->accessToken = config('services.facebook.access_token') ?? env('FACEBOOK_ACCESS_TOKEN');
            $this->pixelId = config('services.facebook.pixel_id') ?? env('FACEBOOK_PIXEL_ID');
            $this->testEventCode = config('services.facebook.test_event_code') ?? env('FACEBOOK_TEST_EVENT_CODE');
        }

        $this->initialized = true;
    }

    public function activeSetting(): ?FacebookCapiSetting
    {
        $this->initialize();

        return $this->setting;
    }

    public function shouldSendPurchaseForContext(string $context): bool
    {
        $this->initialize();

        $trigger = $this->setting->purchase_trigger ?? 'order_created';

        return $trigger === $context;
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

        if (!$this->shouldSendPurchaseForContext($context)) {
            return false;
        }

        return $this->dispatchPurchaseForOrder($order, $amount, $sourceUrl, $context);
    }

    public function sendPurchaseForOrderStatus(Order $order, $status, $amount = null, ?string $sourceUrl = null)
    {
        $this->initialize();

        if (!$this->shouldSendPurchaseForStatus($status)) {
            return false;
        }

        return $this->dispatchPurchaseForOrder($order, $amount, $sourceUrl, $this->setting->purchase_trigger ?? 'order_created');
    }

    protected function dispatchPurchaseForOrder(Order $order, $amount = null, ?string $sourceUrl = null, string $context = 'order_created')
    {
        $cacheKey = 'facebook_capi_purchase_sent_' . $order->id;

        if (Cache::has($cacheKey)) {
            Log::info('Facebook CAPI: Purchase skipped because it was already sent', [
                'order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
            ]);
            return false;
        }

        $order->loadMissing(['customer', 'shipping', 'orderdetails']);

        $shipping = $order->shipping;
        $customer = $order->customer;
        $userData = $this->getUserDataFromRequest();

        if ($customer && $customer->email) {
            $userData['email'] = $customer->email;
        }

        $phone = $shipping->phone ?? $customer->phone ?? null;
        if ($phone) {
            $userData['phone'] = $phone;
        }
        $userData['country_code'] = 'BD';

        $name = $shipping->name ?? $customer->name ?? null;
        if ($name) {
            $nameParts = explode(' ', trim($name), 2);
            $userData['first_name'] = $nameParts[0] ?? null;
            $userData['last_name'] = $nameParts[1] ?? null;
        }

        $contents = $order->orderdetails->map(function ($detail) {
            return [
                'id' => (string) ($detail->product_id ?? $detail->id),
                'quantity' => (int) ($detail->qty ?? 1),
                'item_price' => (float) ($detail->sale_price ?? 0),
            ];
        })->values()->all();

        $result = $this->sendEvent('Purchase', [
            'currency' => 'BDT',
            'value' => (float) ($amount ?? $order->customer_payable_amount ?? $order->amount ?? 0),
            'order_id' => (string) ($order->invoice_id ?? $order->id),
            'content_ids' => collect($contents)->pluck('id')->all(),
            'contents' => $contents,
            'num_items' => (int) $order->orderdetails->sum('qty'),
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

    /**
     * Send event to Facebook Conversion API using direct HTTP request
     * 
     * @param string $eventName Standard event name (Purchase, AddToCart, ViewContent, etc.)
     * @param array $data Event data (currency, value, content_ids, contents, etc.)
     * @param array $userData User data (email, phone, fbp, fbc, etc.)
     * @param array $options Additional options (event_id, event_time, action_source, etc.)
     * @return array|false
     */
    public function sendEvent($eventName, $data = [], $userData = [], $options = [])
    {
        // Lazy initialize credentials
        $this->initialize();

        if ($eventName === 'Purchase') {
            try {
                app(TikTokEventsApiService::class)->sendPurchaseFromPayload($data, $userData, $options);
            } catch (\Throwable $e) {
                Log::warning('TikTok Events API mirror failed for Purchase event', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$this->accessToken || !$this->pixelId) {
            Log::warning('Facebook CAPI: Missing access token or pixel ID');
            return false;
        }

        try {
            // Prepare user data (hash PII)
            $preparedUserData = $this->prepareUserData($userData);

            // Prepare custom data
            $preparedCustomData = $this->prepareCustomData($data, $eventName);

            // Build event payload
            $eventPayload = [
                'event_name' => $eventName,
                'event_time' => $options['event_time'] ?? time(),
                'action_source' => $options['action_source'] ?? 'website',
                'event_source_url' => $options['event_source_url'] ?? request()->fullUrl(),
                'user_data' => $preparedUserData,
                'custom_data' => $preparedCustomData,
            ];

            // Add event ID if provided (for deduplication)
            if ($eventName === 'Purchase' && !$this->shouldSendPurchaseForContext($options['purchase_trigger_context'] ?? 'order_created')) {
                Log::info('Facebook CAPI: Purchase skipped due to trigger setting', [
                    'trigger' => $this->setting->purchase_trigger ?? 'order_created',
                    'context' => $options['purchase_trigger_context'] ?? 'order_created',
                ]);
                return false;
            }

            if (isset($options['event_id'])) {
                $eventPayload['event_id'] = $options['event_id'];
            } elseif (isset($data['event_id'])) {
                $eventPayload['event_id'] = $data['event_id'];
            }

            // Build request payload
            $requestPayload = [
                'data' => [$eventPayload],
                'access_token' => $this->accessToken,
            ];

            // Add test event code if available
            if ($this->testEventCode) {
                $requestPayload['test_event_code'] = $this->testEventCode;
            }

            // Send to Facebook Conversion API without blocking checkout for too long.
            $url = "https://graph.facebook.com/v21.0/{$this->pixelId}/events";
            
            try {
                $response = Http::connectTimeout(1)->timeout(2)->post($url, $requestPayload);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    Log::info('Facebook CAPI: Event sent successfully', [
                        'event_name' => $eventName,
                        'pixel_id' => $this->pixelId,
                        'response' => $responseData
                    ]);

                    return [
                        'success' => true,
                        'event_name' => $eventName,
                        'response' => $responseData
                    ];
                } else {
                    Log::error('Facebook CAPI: API request failed', [
                        'event_name' => $eventName,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);

                    return [
                        'success' => false,
                        'error' => 'API request failed',
                        'status' => $response->status()
                    ];
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Timeout or connection error - silently fail, don't block order
                Log::warning('Facebook CAPI: Request timeout/connection error (non-blocking)', [
                    'event_name' => $eventName,
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Request timeout',
                    'message' => 'Event will be retried or skipped'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Facebook CAPI: Error sending event', [
                'event_name' => $eventName,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Prepare user data (hash PII as required by Facebook)
     */
    protected function prepareUserData($userData)
    {
        $prepared = [];

        // Email (hashed)
        if (isset($userData['email'])) {
            $email = trim(strtolower($userData['email']));
            if (!empty($email) && strlen($email) < 64) {
                $prepared['em'] = hash('sha256', $email);
            }
        }

        // Phone (hashed)
        if (isset($userData['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $userData['phone']);
            if (strlen($phone) === 11 && str_starts_with($phone, '01')) {
                $phone = '88' . $phone;
            }
            if (!empty($phone) && strlen($phone) < 64) {
                $prepared['ph'] = hash('sha256', $phone);
            }
        }

        // First name (hashed)
        if (isset($userData['first_name'])) {
            $firstName = trim(strtolower($userData['first_name']));
            if (!empty($firstName) && strlen($firstName) < 64) {
                $prepared['fn'] = hash('sha256', $firstName);
            }
        }

        // Last name (hashed)
        if (isset($userData['last_name'])) {
            $lastName = trim(strtolower($userData['last_name']));
            if (!empty($lastName) && strlen($lastName) < 64) {
                $prepared['ln'] = hash('sha256', $lastName);
            }
        }

        // City (hashed)
        if (isset($userData['city'])) {
            $city = trim(strtolower($userData['city']));
            if (!empty($city) && strlen($city) < 64) {
                $prepared['ct'] = hash('sha256', $city);
            }
        }

        // State (hashed)
        if (isset($userData['state'])) {
            $state = trim(strtolower($userData['state']));
            if (!empty($state) && strlen($state) < 64) {
                $prepared['st'] = hash('sha256', $state);
            }
        }

        // Zip code (hashed)
        if (isset($userData['zip_code'])) {
            $zipCode = trim($userData['zip_code']);
            if (!empty($zipCode) && strlen($zipCode) < 64) {
                $prepared['zp'] = hash('sha256', $zipCode);
            }
        }

        // Country code (2-letter ISO code)
        if (isset($userData['country_code'])) {
            $prepared['country'] = strtoupper($userData['country_code']);
        }

        // External ID (hashed)
        if (isset($userData['external_id'])) {
            $externalId = (string) $userData['external_id'];
            if (!empty($externalId) && strlen($externalId) < 64) {
                $prepared['external_id'] = hash('sha256', $externalId);
            }
        }

        // Facebook Click ID (fbp) - from _fbp cookie
        if (isset($userData['fbp'])) {
            $prepared['fbp'] = $userData['fbp'];
        }

        // Facebook Browser ID (fbc) - from _fbc cookie
        if (isset($userData['fbc'])) {
            $prepared['fbc'] = $userData['fbc'];
        }

        // Client IP address
        if (isset($userData['client_ip_address'])) {
            $prepared['client_ip_address'] = $userData['client_ip_address'];
        } else {
            $prepared['client_ip_address'] = request()->ip();
        }

        // User agent
        if (isset($userData['client_user_agent'])) {
            $prepared['client_user_agent'] = $userData['client_user_agent'];
        } else {
            $prepared['client_user_agent'] = request()->userAgent();
        }

        return $prepared;
    }

    /**
     * Prepare custom data
     */
    protected function prepareCustomData($data, $eventName)
    {
        $prepared = [];

        // Currency (required for Purchase, AddToCart, InitiateCheckout)
        if (isset($data['currency'])) {
            $prepared['currency'] = strtoupper($data['currency']);
        } else {
            $prepared['currency'] = 'BDT'; // Default currency
        }

        // Value (required for Purchase, AddToCart, InitiateCheckout)
        if (isset($data['value'])) {
            $prepared['value'] = (float) $data['value'];
        }

        // Content IDs (array of product IDs)
        if (isset($data['content_ids'])) {
            $prepared['content_ids'] = is_array($data['content_ids']) ? $data['content_ids'] : [$data['content_ids']];
        }

        // Contents (array of content objects)
        if (isset($data['contents'])) {
            $prepared['contents'] = $data['contents'];
        }

        // Content name
        if (isset($data['content_name'])) {
            $prepared['content_name'] = $data['content_name'];
        }

        // Content category
        if (isset($data['content_category'])) {
            $prepared['content_category'] = $data['content_category'];
        }

        // Number of items
        if (isset($data['num_items'])) {
            $prepared['num_items'] = (int) $data['num_items'];
        }

        // Order ID (for Purchase events)
        if (isset($data['order_id'])) {
            $prepared['order_id'] = (string) $data['order_id'];
        }

        // Search string (for Search events)
        if (isset($data['search_string'])) {
            $prepared['search_string'] = $data['search_string'];
        }

        // Status (for Purchase events)
        if (isset($data['status'])) {
            $prepared['status'] = $data['status'];
        }

        return $prepared;
    }

    /**
     * Send Purchase event
     */
    public function sendPurchase($data, $userData = [], $options = [])
    {
        return $this->sendEvent('Purchase', $data, $userData, $options);
    }

    /**
     * Send AddToCart event
     */
    public function sendAddToCart($data, $userData = [], $options = [])
    {
        return $this->sendEvent('AddToCart', $data, $userData, $options);
    }

    /**
     * Send ViewContent event
     */
    public function sendViewContent($data, $userData = [], $options = [])
    {
        return $this->sendEvent('ViewContent', $data, $userData, $options);
    }

    /**
     * Send InitiateCheckout event
     */
    public function sendInitiateCheckout($data, $userData = [], $options = [])
    {
        return $this->sendEvent('InitiateCheckout', $data, $userData, $options);
    }

    /**
     * Send AddPaymentInfo event
     */
    public function sendAddPaymentInfo($data, $userData = [], $options = [])
    {
        return $this->sendEvent('AddPaymentInfo', $data, $userData, $options);
    }

    /**
     * Send Search event
     */
    public function sendSearch($data, $userData = [], $options = [])
    {
        return $this->sendEvent('Search', $data, $userData, $options);
    }

    /**
     * Get user data from request cookies and session
     */
    public function getUserDataFromRequest()
    {
        $userData = [];

        // Get Facebook Pixel cookies
        if (isset($_COOKIE['_fbp'])) {
            $userData['fbp'] = $_COOKIE['_fbp'];
        }

        if (isset($_COOKIE['_fbc'])) {
            $userData['fbc'] = $_COOKIE['_fbc'];
        }

        // Get authenticated user data if available
        if (auth()->check()) {
            $user = auth()->user();
            
            if (isset($user->email)) {
                $userData['email'] = $user->email;
            }

            if (isset($user->phone)) {
                $userData['phone'] = $user->phone;
            }

            if (isset($user->name)) {
                $nameParts = explode(' ', $user->name, 2);
                if (count($nameParts) > 0) {
                    $userData['first_name'] = $nameParts[0];
                }
                if (count($nameParts) > 1) {
                    $userData['last_name'] = $nameParts[1];
                }
            }

            if (isset($user->id)) {
                $userData['external_id'] = (string) $user->id;
            }
        }

        return $userData;
    }
}
