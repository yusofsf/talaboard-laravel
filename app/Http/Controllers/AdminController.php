<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\InviteCode;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        $users = User::withCount('transactions')
            ->orderByDesc('created_at')->get()
            ->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'phone'       => $u->phone,
                'email'       => $u->email,
                'national_id' => $u->national_id,
                'is_vip'      => $u->is_vip,
                'is_admin'    => $u->is_admin,
                'txn_count'   => $u->transactions_count,
                'created_at'  => Jalali::format($u->created_at, false),
            ]);

        $txns = Transaction::with('user')->orderByDesc('created_at')->limit(200)->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'user_name'      => $t->user?->name,
                'user_phone'     => $t->user?->phone,
                'type'           => $t->type,
                'item_label'     => $t->item_label,
                'quantity'       => (float) $t->quantity,
                'price_per_unit' => $t->price_per_unit,
                'total'          => $t->total,
                'created_at'     => Jalali::format($t->created_at),
            ]);

        $wTxns = WalletTransaction::with('user')->orderByDesc('created_at')->limit(200)->get()
            ->map(fn($w) => [
                'id'          => $w->id,
                'user_name'   => $w->user?->name,
                'user_id'     => $w->user_id,
                'amount'      => $w->amount,
                'type'        => $w->type,
                'description' => $w->description,
                'created_at'  => Jalali::format($w->created_at),
            ]);

        $notifs = Notification::orderByDesc('created_at')->limit(100)->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'title'      => $n->title,
                'body'       => $n->body,
                'type'       => $n->type,
                'user_id'    => $n->user_id,
                'created_at' => Jalali::format($n->created_at),
            ]);

        $invites = InviteCode::with('usedByUser')->orderByDesc('created_at')->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'code'          => $c->code,
                'used_by_name'  => $c->usedByUser?->name,
                'used_by_phone' => $c->usedByUser?->phone,
                'used_at'       => $c->used_at ? Jalali::format($c->used_at) : null,
                'created_at'    => Jalali::format($c->created_at),
            ]);

        $stats = [
            'user_count'  => User::count(),
            'txn_count'   => Transaction::count(),
            'buy_volume'  => Transaction::where('type', 'buy')->sum('total'),
            'sell_volume' => Transaction::where('type', 'sell')->sum('total'),
        ];

        return Inertia::render('Admin/Dashboard', compact('users', 'txns', 'wTxns', 'notifs', 'invites', 'stats'));
    }

    public function setLevel(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        if ($user->id === $request->user()->id) return back();

        $level = $request->input('level', 'regular');
        match ($level) {
            'admin'   => $user->update(['is_vip' => true,  'is_admin' => true]),
            'vip'     => $user->update(['is_vip' => true,  'is_admin' => false]),
            default   => $user->update(['is_vip' => false, 'is_admin' => false]),
        };

        $levelLabel = match ($level) {
            'admin' => 'ادمین', 'vip' => 'ویژه', default => 'عادی',
        };

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'تغییر سطح عضویت',
            'body'    => "سطح حساب شما به «{$levelLabel}» تغییر کرد. تاریخ: " . Jalali::now(),
            'type'    => 'system',
        ]);

        return back()->with('success', 'سطح کاربری به‌روز شد.');
    }

    public function walletCredit(Request $request)
    {
        $request->validate([
            'user_id'     => 'required|exists:users,id',
            'amount'      => 'required|integer|not_in:0',
            'description' => 'nullable|string|max:200',
        ]);

        $user = User::findOrFail($request->user_id);
        WalletTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $request->amount,
            'type'        => $request->amount > 0 ? 'deposit' : 'withdraw',
            'description' => $request->description ?: 'شارژ توسط ادمین',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => $request->amount > 0 ? 'واریز به کیف پول' : 'برداشت از کیف پول',
            'body'    => number_format(abs($request->amount)) . " تومان در تاریخ " . Jalali::now(),
            'type'    => 'wallet',
        ]);

        return back()->with('success', 'تراکنش ثبت شد.');
    }

    public function notify(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'body'  => 'nullable|string',
            'type'  => 'required|in:info,trade,wallet,promo,system',
        ]);

        $target = $request->input('target', 'all');
        $uid    = ($target !== 'all' && is_numeric($target)) ? (int) $target : null;

        Notification::create([
            'title'   => $request->title,
            'body'    => $request->body,
            'type'    => $request->type,
            'user_id' => $uid,
        ]);

        return back()->with('success', 'اعلان ارسال شد.');
    }

    public function deleteNotification(int $id)
    {
        Notification::findOrFail($id)->delete();
        return back()->with('success', 'اعلان حذف شد.');
    }

    public function generateCode()
    {
        InviteCode::create(['code' => strtoupper(Str::random(8))]);
        return back()->with('success', 'کد جدید ایجاد شد.');
    }
}
