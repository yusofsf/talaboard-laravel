<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $txns = $user->walletTransactions()->get()->map(fn($t) => [
            'id'          => $t->id,
            'amount'      => $t->amount,
            'type'        => $t->type,
            'description' => $t->description,
            'created_at'  => Jalali::format($t->created_at),
        ]);

        $withdrawals = WithdrawalRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')->get()
            ->map(fn ($w) => [
                'id'          => $w->id,
                'amount'      => $w->amount,
                'card_number' => $w->card_number,
                'shaba'       => $w->shaba,
                'status'      => $w->status,
                'admin_note'  => $w->admin_note,
                'created_at'  => Jalali::format($w->created_at),
            ]);

        return Inertia::render('Wallet', [
            'balance'     => $user->walletBalance(),
            'txns'        => $txns,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'amount'      => 'required|integer|min:1000',
            'card_number' => 'required|string|max:30',
            'shaba'       => 'required|string|max:30',
        ]);

        if ($user->walletBalance() <= 0) {
            return back()->withErrors(['amount' => 'موجودی کیف پول شما صفر است.']);
        }
        if ($user->walletBalance() < $request->amount) {
            return back()->withErrors(['amount' => 'موجودی کیف پول شما کافی نیست.']);
        }

        $admins = User::where('is_admin', true)->get();

        DB::transaction(function () use ($user, $request, $admins) {
            $withdrawal = WithdrawalRequest::create([
                'user_id'     => $user->id,
                'amount'      => $request->amount,
                'card_number' => $request->card_number,
                'shaba'       => $request->shaba,
                'status'      => 'pending',
            ]);

            WalletTransaction::create([
                'user_id'     => $user->id,
                'amount'      => -$request->amount,
                'type'        => 'withdraw',
                'description' => "درخواست تسویه حساب #{$withdrawal->id}",
            ]);

            Notification::create([
                'user_id' => $user->id,
                'title'   => 'درخواست تسویه حساب ثبت شد',
                'body'    => number_format($request->amount) . ' تومان — تاریخ: ' . Jalali::now() . ' — در حال بررسی.',
                'type'    => 'wallet',
            ]);

            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title'   => "درخواست تسویه حساب — {$user->name}",
                    'body'    => number_format($request->amount) . ' تومان — تاریخ: ' . Jalali::now(),
                    'type'    => 'wallet',
                ]);
            }
        });

        ActivityLog::record('withdrawal_request', 'wallet',
            "درخواست تسویه حساب " . number_format($request->amount) . " تومان — کاربر: {$user->name}", $user->id);

        try {
            $this->sms->send($user->phone, 'درخواست تسویه حساب ' . number_format($request->amount) . ' تومانی شما ثبت شد و در حال بررسی است.');
            foreach ($admins as $admin) {
                $this->sms->send($admin->phone, "درخواست تسویه حساب جدید: {$user->name} — " . number_format($request->amount) . ' تومان.');
            }
        } catch (\Exception) {}

        return back()->with('success', 'درخواست تسویه حساب شما ثبت شد.');
    }
}
