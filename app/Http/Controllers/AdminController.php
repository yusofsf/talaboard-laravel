<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\InviteCode;
use App\Models\Notification;
use App\Models\SilverDeliveryRequest;
use App\Models\SilverLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'membership_level' => $u->membership_level,
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

        $memberApplications = User::where('membership_status', 'pending')
            ->orderByDesc('updated_at')->get()
            ->map(fn($u) => [
                'id'                  => $u->id,
                'name'                 => $u->name,
                'phone'                => $u->phone,
                'national_id'          => $u->national_id,
                'national_id_doc'      => $u->national_id_doc ? Storage::url($u->national_id_doc) : null,
                'identity_doc'         => $u->identity_doc ? Storage::url($u->identity_doc) : null,
                'verification_video'   => $u->verification_video ? Storage::url($u->verification_video) : null,
                'submitted_at'         => Jalali::format($u->updated_at),
            ]);

        $deliveryRequests = SilverDeliveryRequest::with('user')
            ->where('status', '!=', 'delivered')
            ->orderByDesc('created_at')->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'user_name'      => $r->user?->name,
                'user_phone'     => $r->user?->phone,
                'purity'         => $r->purity,
                'grams'          => (float) $r->grams,
                'recipient_name' => $r->recipient_name,
                'phone'          => $r->phone,
                'address'        => $r->address,
                'status'         => $r->status,
                'created_at'     => Jalali::format($r->created_at),
            ]);

        return Inertia::render('Admin/Dashboard', compact('users', 'txns', 'wTxns', 'notifs', 'invites', 'stats', 'memberApplications', 'deliveryRequests'));
    }

    public function setLevel(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        if ($user->id === $request->user()->id) return back();

        $level = $request->input('level', 'regular');
        match ($level) {
            'admin'   => $user->update(['is_vip' => true,  'is_admin' => true,  'membership_level' => 2]),
            'vip'     => $user->update(['is_vip' => true,  'is_admin' => false, 'membership_level' => 2]),
            default   => $user->update(['is_vip' => false, 'is_admin' => false, 'membership_level' => 1]),
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

    public function membershipApprove(int $uid)
    {
        $user = User::findOrFail($uid);
        $user->update([
            'is_vip'            => true,
            'membership_level'  => 2,
            'membership_status' => 'approved',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => '👑 عضویت ویژه تأیید شد',
            'body'    => 'درخواست عضویت ویژه‌ی شما در تاریخ ' . Jalali::now() . ' تأیید شد.',
            'type'    => 'promo',
        ]);

        return back()->with('success', 'عضویت ویژه تأیید شد.');
    }

    public function membershipReject(int $uid)
    {
        $user = User::findOrFail($uid);
        $user->update(['membership_status' => 'rejected']);

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'درخواست عضویت ویژه رد شد',
            'body'    => 'درخواست عضویت ویژه‌ی شما در تاریخ ' . Jalali::now() . ' رد شد. می‌توانید دوباره درخواست دهید.',
            'type'    => 'system',
        ]);

        return back()->with('success', 'درخواست رد شد.');
    }

    public function deliveryUpdate(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:approved,shipped,delivered,rejected']);

        $delivery = SilverDeliveryRequest::findOrFail($id);

        // در صورت رد درخواست، نقره‌ی رزرو‌شده به موجودی کاربر برمی‌گردد
        if ($request->status === 'rejected' && $delivery->status !== 'rejected') {
            SilverLedger::create([
                'user_id' => $delivery->user_id, 'purity' => $delivery->purity, 'grams' => $delivery->grams,
                'type' => 'delivery_refund', 'reference_type' => SilverDeliveryRequest::class, 'reference_id' => $delivery->id,
                'description' => "بازگشت نقره — رد درخواست تحویل #{$delivery->id}",
            ]);
        }

        $delivery->update(['status' => $request->status]);

        $statusLabel = [
            'approved'  => 'تأیید شد',
            'shipped'   => 'ارسال شد',
            'delivered' => 'تحویل داده شد',
            'rejected'  => 'رد شد',
        ][$request->status];

        Notification::create([
            'user_id' => $delivery->user_id,
            'title'   => 'وضعیت درخواست تحویل فیزیکی نقره',
            'body'    => "درخواست شما «{$statusLabel}». تاریخ: " . Jalali::now(),
            'type'    => 'system',
        ]);

        return back()->with('success', 'وضعیت به‌روزرسانی شد.');
    }
}
