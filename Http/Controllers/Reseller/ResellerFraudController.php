<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ResellerFraudController extends Controller
{
    /**
     * Display manual fraud check page for reseller.
     *
     * @return \Illuminate\View\View
     */
    public function manualFraudCheckPage()
    {
        $user = Auth::guard('admin')->user();

        if (!$user || (!$user->hasRole('reseller') && $user->role !== 'reseller')) {
            return redirect()->route('reseller.dashboard');
        }

        return view('reseller.fraud.manual_check', compact('user'));
    }

    /**
     * Perform manual fraud check.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function manualFraudCheck(Request $request)
    {
        $user = Auth::guard('admin')->user();

        if (!$user || (!$user->hasRole('reseller') && $user->role !== 'reseller')) {
            return redirect()->route('reseller.dashboard');
        }

        $mobile = trim((string) $request->input('mobile'));

        if ($mobile === '') {
            return back()->with('error', 'দয়া করে একটি মোবাইল নাম্বার লিখুন');
        }

        $generalSetting = GeneralSetting::where('status', 1)->first();
        $apiKey = $generalSetting->fraud_api_key ?? null;
        $fallbackData = $this->buildLocalCourierStats($mobile);

        if (!$apiKey) {
            $data = $fallbackData;
            $error = 'Fraud API Key সেটিংস প্যানেলে সেট করা নেই';

            return view('reseller.fraud.manual_check', compact('mobile', 'data', 'user', 'error'));
        }

        $apiUrl = 'https://www.creativedesign.com.bd/api/v1/check-fraud';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($apiUrl, [
                'phone' => $mobile,
            ]);

            $res = $response->json();

            if (isset($res['status']) && $res['status'] === 'success') {
                if (isset($res['is_fraud']) && $res['is_fraud'] === true) {
                    $data = [
                        'is_fraud' => true,
                        'message' => $res['message'] ?? 'Fraud detected',
                    ];
                } else {
                    $data = $res['data'] ?? $fallbackData;
                }

                return view('reseller.fraud.manual_check', compact('mobile', 'data', 'user'));
            }

            $data = $fallbackData;
            $error = $res['message'] ?? 'Fraud check ব্যর্থ হয়েছে';

            return view('reseller.fraud.manual_check', compact('mobile', 'data', 'user', 'error'));
        } catch (\Exception $e) {
            $data = $fallbackData;
            $error = 'API Error: ' . $e->getMessage();

            return view('reseller.fraud.manual_check', compact('mobile', 'data', 'user', 'error'));
        }
    }

    private function buildLocalCourierStats(string $mobile): array
    {
        $normalizedMobile = $this->normalizePhone($mobile);
        $searchTokens = array_filter(array_unique([
            $normalizedMobile,
            ltrim($normalizedMobile, '0'),
            substr($normalizedMobile, -10),
            substr($normalizedMobile, -11),
            '+88' . substr($normalizedMobile, -11),
        ]));
        $successStatusIds = $this->resolveStatusIds(
            ['delivered', 'completed', 'partial delivered', 'partial-delivered', 'partial_delivered'],
            [6, 12, 13]
        );
        $cancelStatusIds = $this->resolveStatusIds(
            ['cancelled', 'canceled', 'returned', 'return'],
            [11, 14]
        );

        $orders = Order::query()
            ->whereNotNull('courier_type')
            ->with('shipping')
            ->whereHas('shipping', function ($query) use ($searchTokens) {
                $query->where(function ($innerQuery) use ($searchTokens) {
                    foreach ($searchTokens as $token) {
                        if ($token !== '') {
                            $innerQuery->orWhere('phone', 'like', '%' . $token . '%');
                        }
                    }
                });
            })
            ->get()
            ->filter(function ($order) use ($searchTokens) {
                $shippingPhone = $this->normalizePhone((string) optional($order->shipping)->phone);

                foreach ($searchTokens as $token) {
                    $normalizedToken = $this->normalizePhone((string) $token);
                    if ($normalizedToken !== '' && str_contains($shippingPhone, $normalizedToken)) {
                        return true;
                    }
                }

                return false;
            });

        $couriers = ['pathao', 'steadfast', 'redx'];
        $data = [];
        $summaryTotal = 0;
        $summarySuccess = 0;
        $summaryCancelled = 0;

        foreach ($couriers as $courier) {
            $courierOrders = $orders->filter(function ($order) use ($courier) {
                return str_contains(strtolower((string) $order->courier_type), $courier);
            });

            $total = $courierOrders->count();
            $success = $courierOrders->filter(function ($order) use ($successStatusIds) {
                return in_array((int) $order->order_status, $successStatusIds, true);
            })->count();
            $cancelled = $courierOrders->filter(function ($order) use ($cancelStatusIds) {
                return in_array((int) $order->order_status, $cancelStatusIds, true);
            })->count();
            $ratio = $total > 0 ? round(($success / $total) * 100, 2) : 0;

            $data[$courier] = [
                'total_parcel' => $total,
                'success_parcel' => $success,
                'cancelled_parcel' => $cancelled,
                'success_ratio' => $ratio,
            ];

            $summaryTotal += $total;
            $summarySuccess += $success;
            $summaryCancelled += $cancelled;
        }

        $data['summary'] = [
            'total_parcel' => $summaryTotal,
            'success_parcel' => $summarySuccess,
            'cancelled_parcel' => $summaryCancelled,
            'success_ratio' => $summaryTotal > 0 ? round(($summarySuccess / $summaryTotal) * 100, 2) : 0,
        ];

        return $data;
    }

    private function resolveStatusIds(array $aliases, array $fallbacks = []): array
    {
        $ids = OrderStatus::query()
            ->where(function ($query) use ($aliases) {
                foreach ($aliases as $alias) {
                    $normalized = strtolower(trim($alias));
                    $query->orWhereRaw('LOWER(name) = ?', [$normalized])
                        ->orWhereRaw('LOWER(slug) = ?', [str_replace(' ', '-', $normalized)]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($ids, $fallbacks)));
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }
}
