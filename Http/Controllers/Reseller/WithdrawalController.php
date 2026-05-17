<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerWithdrawal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    public function index()
    {
        $user = Auth::guard('admin')->user();
        $withdrawals = ResellerWithdrawal::where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return view('reseller.withdrawals.index', compact('user', 'withdrawals'));
    }

    public function store(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $walletBalance = $user->wallet_balance ?? 0;

        $min = config('app.reseller_min_withdraw', 100);
        $request->validate([
            'amount' => 'required|numeric|min:' . $min,
            'payout_method' => 'required|string|max:50',
            'account_name' => 'nullable|string|max:191',
            'account_number' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:500',
        ]);

        $amount = round((float) $request->amount, 2);

        if ($amount > $walletBalance) {
            return back()->with('error', 'You do not have enough balance.');
        }

        DB::transaction(function () use ($user, $amount, $request) {
            // Hold balance immediately
            $user->wallet_balance -= $amount;
            $user->save();

            ResellerWithdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'charge' => 0,
                'payout_method' => $request->payout_method,
                'account_name' => $request->account_name,
                'account_number' => $request->account_number,
                'note' => $request->note,
                'status' => 'pending',
            ]);
        });

        return back()->with('success', 'Withdraw request submitted successfully.');
    }
}
