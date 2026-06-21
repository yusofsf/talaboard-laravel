<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\SilverDeliveryRequest;
use App\Models\SilverLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function __construct(private SmsService $sms) {}

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
                'wallet_balance' => $u->walletBalance(),
                'gold_balance'   => $u->goldBalance(),
                'silver_balance' => ['999' => $u->silverBalance('999'), '995' => $u->silverBalance('995')],
                'created_at'  => Jalali::format($u->created_at, false),
            ]);

        $txns = Transaction::with('user')->orderByDesc('created_at')->limit(200)->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'user_id'        => $t->user_id,
                'user_name'      => $t->user?->name,
                'user_phone'     => $t->user?->phone,
                'type'           => $t->type,
                'item'           => $t->item,
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
                'name'                => $u->name,
                'phone'               => $u->phone,
                'national_id'         => $u->national_id,
                'birth_date'          => $u->birth_date?->format('Y-m-d'),
                'residence_address'   => $u->residence_address,
                'national_id_doc'     => $u->national_id_doc ? Storage::url($u->national_id_doc) : null,
                'identity_doc'        => $u->identity_doc ? Storage::url($u->identity_doc) : null,
                'verification_video'  => $u->verification_video ? Storage::url($u->verification_video) : null,
                'submitted_at'        => Jalali::format($u->updated_at),
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

        return Inertia::render('Admin/Dashboard', compact('users', 'txns', 'wTxns', 'notifs', 'stats', 'memberApplications', 'deliveryRequests'));
    }

    public function setLevel(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        if ($user->id === $request->user()->id) return back();

        $level = $request->input('level', 'regular');
        match ($level) {
            'vip_admin' => $user->update(['is_vip' => true,  'is_admin' => true,  'membership_level' => 2]),
            'admin'     => $user->update(['is_vip' => false, 'is_admin' => true]),
            'vip'       => $user->update(['is_vip' => true,  'is_admin' => false, 'membership_level' => 2]),
            default     => $user->update(['is_vip' => false, 'is_admin' => false, 'membership_level' => 1]),
        };

        $levelLabel = match ($level) {
            'vip_admin' => 'ویژه و ادمین',
            'admin'     => 'ادمین',
            'vip'       => 'ویژه',
            default     => 'عادی',
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

    /** ادمین موجودی انبار طلا یا نقره‌ی یک کاربر را افزایش/کاهش می‌دهد. */
    public function inventoryAdjust(Request $request, int $uid)
    {
        $request->validate([
            'metal'       => 'required|in:gold,silver',
            'purity'      => 'required_if:metal,silver|in:999,995',
            'grams'       => 'required|numeric|not_in:0',
            'description' => 'nullable|string|max:200',
        ]);

        $user = User::findOrFail($uid);
        $grams = (float) $request->grams;
        $desc  = $request->description ?: 'اصلاح موجودی توسط ادمین';

        if ($request->metal === 'gold') {
            GoldLedger::create([
                'user_id' => $user->id, 'grams' => $grams, 'type' => 'admin_adjust', 'description' => $desc,
            ]);
        } else {
            SilverLedger::create([
                'user_id' => $user->id, 'purity' => $request->purity, 'grams' => $grams,
                'type' => 'admin_adjust', 'description' => $desc,
            ]);
        }

        $metalLabel = $request->metal === 'gold' ? 'طلا' : ('نقره ' . $request->purity);
        Notification::create([
            'user_id' => $user->id,
            'title'   => 'تغییر موجودی انبار',
            'body'    => ($grams > 0 ? 'افزایش' : 'کاهش') . " {$metalLabel}: " . abs($grams) . ' گرم — ' . Jalali::now(),
            'type'    => 'system',
        ]);

        return back()->with('success', 'موجودی انبار به‌روزرسانی شد.');
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

    public function membershipApprove(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        $user->update([
            'is_vip'            => true,
            'membership_level'  => 2,
            'membership_status' => 'approved',
        ]);

        $msg  = trim((string) $request->input('message', ''));
        $body = 'درخواست عضویت ویژه‌ی شما در تاریخ ' . Jalali::now() . ' تأیید شد.';
        if ($msg !== '') $body .= "\nپیام ادمین: {$msg}";

        Notification::create([
            'user_id' => $user->id,
            'title'   => '👑 عضویت ویژه تأیید شد',
            'body'    => $body,
            'type'    => 'promo',
        ]);

        try {
            $this->sms->send($user->phone, "عضویت ویژه‌ی شما در آبشده صفرپور تأیید شد." . ($msg !== '' ? " {$msg}" : ''));
        } catch (\Exception) {}

        return back()->with('success', 'عضویت ویژه تأیید شد.');
    }

    public function membershipReject(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        $user->update(['membership_status' => 'rejected']);

        $msg  = trim((string) $request->input('message', ''));
        $body = 'درخواست عضویت ویژه‌ی شما در تاریخ ' . Jalali::now() . ' رد شد. می‌توانید دوباره درخواست دهید.';
        if ($msg !== '') $body .= "\nپیام ادمین: {$msg}";

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'درخواست عضویت ویژه رد شد',
            'body'    => $body,
            'type'    => 'system',
        ]);

        try {
            $this->sms->send($user->phone, "درخواست عضویت ویژه‌ی شما رد شد." . ($msg !== '' ? " {$msg}" : ''));
        } catch (\Exception) {}

        return back()->with('success', 'درخواست رد شد.');
    }

    public function deliveryUpdate(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:approved,shipped,delivered,rejected']);

        $delivery = SilverDeliveryRequest::with('user')->findOrFail($id);

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

        try {
            $this->sms->sendDeliveryStatusUpdate($delivery->user->phone, $delivery->user->name, $statusLabel);
        } catch (\Exception) {}

        return back()->with('success', 'وضعیت به‌روزرسانی شد.');
    }

    public function userUpdate(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);

        $request->validate([
            'name'        => 'required|string|max:100',
            'phone'       => 'required|string|unique:users,phone,' . $user->id,
            'email'       => 'nullable|email',
            'national_id' => 'nullable|string|max:10',
        ]);

        $user->update($request->only('name', 'phone', 'email', 'national_id'));

        return back()->with('success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function userDestroy(int $uid)
    {
        $user = User::findOrFail($uid);
        if ($user->is_admin) {
            return back()->with('error', 'حذف حساب ادمین مجاز نیست.');
        }
        $user->delete();
        return back()->with('success', 'کاربر حذف شد.');
    }

    public function transactionUpdate(Request $request, int $id)
    {
        $txn = Transaction::findOrFail($id);

        $request->validate([
            'type'           => 'required|in:buy,sell',
            'quantity'       => 'required|numeric|min:0.001',
            'price_per_unit' => 'required|integer|min:1',
        ]);

        $total = (int) round($request->quantity * $request->price_per_unit);

        $txn->update([
            'type'           => $request->type,
            'quantity'       => $request->quantity,
            'price_per_unit' => $request->price_per_unit,
            'total'          => $total,
        ]);

        return back()->with('success', 'معامله ویرایش شد.');
    }

    public function transactionDestroy(int $id)
    {
        Transaction::findOrFail($id)->delete();
        return back()->with('success', 'معامله حذف شد.');
    }
}
