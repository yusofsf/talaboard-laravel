<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\DepositRequest;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\SilverDeliveryRequest;
use App\Models\SilverLedger;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Transaction;
use App\Models\TradeRoomOffer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'date_raw'       => $t->created_at->format('Y-m-d'),
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
                'date_raw'    => $w->created_at->format('Y-m-d'),
            ]);

        $totalUserCount = User::count();
        $notifs = Notification::withCount('reads')->orderByDesc('created_at')->limit(100)->get()
            ->map(fn($n) => [
                'id'           => $n->id,
                'title'        => $n->title,
                'body'         => $n->body,
                'type'         => $n->type,
                'user_id'      => $n->user_id,
                'read_count'   => $n->reads_count,
                'target_count' => $n->user_id ? 1 : $totalUserCount,
                'created_at'   => Jalali::format($n->created_at),
            ]);

        $stats = [
            'user_count'  => User::count(),
            'txn_count'   => Transaction::where('status', 'active')->count(),
            'buy_volume'  => Transaction::where('status', 'active')->where('type', 'buy')->sum('total'),
            'sell_volume' => Transaction::where('status', 'active')->where('type', 'sell')->sum('total'),
        ];

        $memberApplications = User::where('membership_status', 'pending')
            ->orderByDesc('updated_at')->get()
            ->map(fn($u) => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'phone'               => $u->phone,
                'national_id'         => $u->national_id,
                'birth_date'          => $u->birth_date ? Jalali::format($u->birth_date, false) : null,
                'residence_address'   => $u->residence_address,
                'national_id_doc'     => $u->national_id_doc ? Storage::url($u->national_id_doc) : null,
                'identity_doc'        => $u->identity_doc ? Storage::url($u->identity_doc) : null,
                'verification_video'  => $u->verification_video ? Storage::url($u->verification_video) : null,
                'submitted_at'        => Jalali::format($u->updated_at),
            ]);

        $vipMembers = User::where('is_vip', true)
            ->orWhere('membership_level', 2)
            ->orderByDesc('updated_at')->get()
            ->map(fn ($u) => [
                'id'                  => $u->id,
                'name'                => $u->name,
                'phone'               => $u->phone,
                'email'               => $u->email,
                'national_id'         => $u->national_id,
                'birth_date'          => $u->birth_date ? Jalali::format($u->birth_date, false) : null,
                'residence_address'   => $u->residence_address,
                'national_id_doc'     => $u->national_id_doc ? Storage::url($u->national_id_doc) : null,
                'identity_doc'        => $u->identity_doc ? Storage::url($u->identity_doc) : null,
                'verification_video'  => $u->verification_video ? Storage::url($u->verification_video) : null,
                'membership_status'   => $u->membership_status,
                'approved_at'         => Jalali::format($u->updated_at, false),
            ]);

        // درخواست‌هایی که به نتیجه‌ی نهایی رسیده‌اند (تحویل‌شده یا رد‌شده) از این لیست بسته/حذف می‌شوند
        $deliveryRequests = SilverDeliveryRequest::with('user')
            ->whereNotIn('status', ['delivered', 'rejected'])
            ->orderByDesc('created_at')->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'user_name'      => $r->user?->name,
                'user_phone'     => $r->user?->phone,
                'metal'          => $r->metal,
                'purity'         => $r->purity,
                'grams'          => (float) $r->grams,
                'recipient_name' => $r->recipient_name,
                'phone'          => $r->phone,
                'address'        => $r->address,
                'delivery_method' => $r->delivery_method ?? 'address',
                'status'         => $r->status,
                'created_at'     => Jalali::format($r->created_at),
                'date_raw'       => $r->created_at->format('Y-m-d'),
            ]);

        $withdrawalRequests = WithdrawalRequest::with('user')
            ->where('status', 'pending')
            ->orderByDesc('created_at')->get()
            ->map(fn ($w) => [
                'id'          => $w->id,
                'user_name'   => $w->user?->name,
                'user_phone'  => $w->user?->phone,
                'amount'      => $w->amount,
                'card_number' => $w->card_number,
                'shaba'       => $w->shaba,
                'status'      => $w->status,
                'created_at'  => Jalali::format($w->created_at),
                'date_raw'    => $w->created_at->format('Y-m-d'),
            ]);

        $depositRequests = DepositRequest::with('user')
            ->where('status', 'pending')
            ->orderByDesc('created_at')->get()
            ->map(fn ($d) => [
                'id'         => $d->id,
                'user_name'  => $d->user?->name,
                'user_phone' => $d->user?->phone,
                'amount'     => $d->amount,
                'note'       => $d->note,
                'status'     => $d->status,
                'created_at' => Jalali::format($d->created_at),
                'date_raw'   => $d->created_at->format('Y-m-d'),
            ]);

        $allTrades = $this->allTradesHistory();

        $activityLogs = ActivityLog::with('user')->orderByDesc('id')->limit(400)->get()
            ->map(fn ($l) => [
                'id'          => $l->id,
                'action'      => $l->action,
                'category'    => $l->category,
                'description' => $l->description,
                'ip'          => $l->ip,
                'user_name'   => $l->user?->name,
                'created_at'  => Jalali::format($l->created_at),
                'date_raw'    => optional($l->created_at)->format('Y-m-d'),
            ]);

        $tickets = Ticket::with('user')->withCount('messages')->orderByDesc('updated_at')->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'user_name'  => $t->user?->name,
                'user_phone' => $t->user?->phone,
                'subject'    => $t->subject,
                'status'     => $t->status,
                'msg_count'  => $t->messages_count,
                'created_at' => Jalali::format($t->created_at),
                'date_raw'   => $t->created_at->format('Y-m-d'),
            ]);

        $settings = [
            'trade_room_commission_percent' => (float) Setting::get('trade_room_commission_percent', 0.1),
        ];

        return Inertia::render('Admin/Dashboard', compact('users', 'txns', 'wTxns', 'notifs', 'stats', 'memberApplications', 'vipMembers', 'deliveryRequests', 'withdrawalRequests', 'depositRequests', 'allTrades', 'activityLogs', 'tickets', 'settings'));
    }

    /** ریز معاملات یک کاربر خاص (فروشگاه + اتاق معاملاتی) برای مشاهده و خروجی PDF ادمین. */
    public function userTrades(int $uid)
    {
        $user = User::findOrFail($uid);

        $shop = Transaction::where('user_id', $uid)->orderByDesc('created_at')->get()
            ->map(fn ($t) => [
                'id'          => 'shop-' . $t->id,
                'source'      => 'فروشگاه',
                'side'        => $t->type,
                'item_label'  => $t->item_label,
                'quantity'    => (float) $t->quantity,
                'price'       => $t->price_per_unit,
                'total'       => $t->total,
                'role'        => '—',
                'status'      => $t->status ?? 'active',
                'admin_note'  => $t->admin_note,
                'created_at'  => Jalali::format($t->created_at),
                'date_raw'    => $t->created_at->format('Y-m-d'),
                'sort_at'     => $t->created_at,
            ]);

        // معاملات اتاق معاملاتی که این کاربر در آن‌ها پیشنهاددهنده یا طرف مقابل بوده
        // (سکه‌ها به‌صورت Transaction در بخش فروشگاه می‌آیند، اینجا حذف می‌شوند تا دوبار شمرده نشوند)
        $room = TradeRoomOffer::with(['user', 'counterparty'])
            ->where('status', 'completed')->where('metal', '!=', 'coin')
            ->where(fn ($q) => $q->where('user_id', $uid)->orWhere('counterparty_id', $uid))
            ->orderByDesc('completed_at')->get()
            ->map(function ($o) use ($uid) {
                $isOfferer = $o->user_id === $uid;
                // نقش واقعی این کاربر در معامله بسته به side پیشنهاد و اینکه پیشنهاددهنده بوده یا پذیرنده
                $userIsSeller = ($o->side === 'sell') === $isOfferer;
                return [
                    'id'          => 'room-' . $o->id,
                    'source'      => 'اتاق معاملاتی',
                    'side'        => $userIsSeller ? 'sell' : 'buy',
                    'item_label'  => $o->metal === 'gold' ? 'طلا (گرم)' : ('نقره ' . $o->purity . ' (گرم)'),
                    'quantity'    => (float) $o->grams,
                    'price'       => $o->price_per_gram,
                    'total'       => $o->total(),
                    'role'        => $isOfferer ? 'پیشنهاددهنده' : 'پذیرنده',
                    'status'      => 'active',
                    'admin_note'  => null,
                    'created_at'  => Jalali::format($o->completed_at ?? $o->created_at),
                    'date_raw'    => ($o->completed_at ?? $o->created_at)->format('Y-m-d'),
                    'sort_at'     => $o->completed_at ?? $o->created_at,
                ];
            });

        $trades = $shop->concat($room)->sortByDesc('sort_at')
            ->map(function ($t) { unset($t['sort_at']); return $t; })
            ->values()->all();

        return Inertia::render('Admin/UserTrades', [
            'subject' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'phone'          => $user->phone,
                'wallet_balance' => $user->walletBalance(),
                'gold_balance'   => $user->goldBalance(),
                'silver_balance' => ['999' => $user->silverBalance('999'), '995' => $user->silverBalance('995')],
            ],
            'trades' => $trades,
        ]);
    }

    /** کاربرانی که در ۵ دقیقه‌ی اخیر درخواستی فرستاده‌اند (UpdateLastSeen middleware) — فقط برای ادمین. */
    public function onlineUsers()
    {
        $users = User::whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn ($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'phone'        => $u->phone,
                'is_vip'       => $u->is_vip,
                'is_admin'     => $u->is_admin,
                'last_seen_at' => Jalali::format($u->last_seen_at),
                'seconds_ago'  => now()->diffInSeconds($u->last_seen_at),
            ]);

        return Inertia::render('Admin/OnlineUsers', ['users' => $users]);
    }

    public function ticketShow(int $id)
    {
        $ticket = Ticket::with(['user', 'messages.user'])->findOrFail($id);

        return Inertia::render('Admin/TicketShow', [
            'ticket' => [
                'id'         => $ticket->id,
                'subject'    => $ticket->subject,
                'status'     => $ticket->status,
                'user_name'  => $ticket->user?->name,
                'user_phone' => $ticket->user?->phone,
                'messages'   => $ticket->messages->map(fn ($m) => [
                    'id'         => $m->id,
                    'is_admin'   => $m->is_admin,
                    // نام ادمین فقط برای ادمین‌ها نمایش داده می‌شود
                    'admin_name' => $m->is_admin ? $m->user?->name : null,
                    'message'    => $m->message,
                    'created_at' => Jalali::format($m->created_at),
                ]),
            ],
        ]);
    }

    public function ticketReply(Request $request, int $id)
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $ticket = Ticket::with('user')->findOrFail($id);
        $admin  = $request->user();

        if (in_array($ticket->status, ['closed', 'resolved'])) {
            return back()->with('error', 'این تیکت بسته شده و امکان پاسخ‌دهی ندارد.');
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id, 'user_id' => $admin->id, 'is_admin' => true,
            'message'   => $request->message, 'created_at' => now(),
        ]);
        $ticket->update(['status' => 'answered']);

        // نوتیف به کاربر — بدون نام ادمین
        Notification::create([
            'user_id' => $ticket->user_id,
            'title'   => "پاسخ جدید به تیکت «{$ticket->subject}»",
            'body'    => "پاسخ ادمین: {$request->message}\nتاریخ: " . Jalali::now(),
            'type'    => 'system',
        ]);

        // نوتیف به سایر ادمین‌ها — با نام ادمین پاسخ‌دهنده
        $this->notifyOtherAdmins($request, 'پاسخ به تیکت توسط ادمین',
            "{$admin->name} به تیکت «{$ticket->subject}» (کاربر: {$ticket->user?->name}) پاسخ داد. تاریخ: " . Jalali::now());

        return back()->with('success', 'پاسخ ارسال شد.');
    }

    public function ticketClose(Request $request, int $id)
    {
        $ticket = Ticket::with('user')->findOrFail($id);
        $ticket->update(['status' => 'closed']);

        Notification::create([
            'user_id' => $ticket->user_id,
            'title'   => "تیکت «{$ticket->subject}» بسته شد",
            'body'    => 'این تیکت توسط پشتیبانی بسته شد. در صورت نیاز می‌توانید تیکت جدید ثبت کنید. تاریخ: ' . Jalali::now(),
            'type'    => 'system',
        ]);

        $this->notifyOtherAdmins($request, 'بستن تیکت توسط ادمین',
            "{$request->user()->name} تیکت «{$ticket->subject}» (کاربر: {$ticket->user?->name}) را بست. تاریخ: " . Jalali::now());

        return back()->with('success', 'تیکت بسته شد.');
    }

    /** تاریخچه‌ی کلی معاملات (فروشگاه + اتاق معاملاتی) برای ادمین — یک لیست واحد، جدیدترین اول. */
    private function allTradesHistory(): array
    {
        $shop = Transaction::with('user')->get()->map(fn ($t) => [
            'id'                 => 'shop-' . $t->id,
            'ref_id'             => $t->id,
            'source'             => 'shop',
            'source_label'       => 'فروشگاه',
            'side'               => $t->type,
            'item_label'         => $t->item_label,
            'quantity'           => (float) $t->quantity,
            'price'              => $t->price_per_unit,
            'total'              => $t->total,
            'user_name'          => $t->user?->name,
            'counterparty_name'  => null,
            'status'             => $t->status ?? 'active',
            'admin_note'         => $t->admin_note,
            'can_reject'         => ($t->status ?? 'active') === 'active',
            'sort_at'            => $t->created_at,
        ]);

        // معاملات تکمیل‌شده + معاملاتی که ادمین برگشت زده (cancelled با یادداشت ادمین)
        // سکه‌ها از این لیست کنار گذاشته می‌شوند چون به‌صورت ردیف Transaction در بخش فروشگاه نمایش داده می‌شوند (جلوگیری از شمارش دوگانه).
        $room = TradeRoomOffer::with(['user', 'counterparty'])
            ->where('metal', '!=', 'coin')
            ->where(fn ($q) => $q->where('status', 'completed')
                ->orWhere(fn ($q2) => $q2->where('status', 'cancelled')->whereNotNull('admin_note')))
            ->get()
            ->map(fn ($o) => [
                'id'                 => 'room-' . $o->id,
                'ref_id'             => $o->id,
                'source'             => 'room',
                'source_label'       => 'اتاق معاملاتی',
                'side'               => $o->side,
                'item_label'         => $o->metal === 'gold' ? 'طلا (گرم)' : ('نقره ' . $o->purity . ' (گرم)'),
                'quantity'           => (float) $o->grams,
                'price'              => $o->price_per_gram,
                'total'              => $o->total(),
                'user_name'          => $o->user?->name,
                'counterparty_name'  => $o->counterparty?->name,
                'status'             => $o->status === 'completed' ? 'active' : 'rejected',
                'admin_note'         => $o->admin_note,
                'can_reject'         => $o->status === 'completed',
                'sort_at'            => $o->completed_at ?? $o->created_at,
            ]);

        return $shop->concat($room)
            ->sortByDesc('sort_at')
            ->take(300)
            ->map(function ($t) {
                $sortAt = $t['sort_at'];
                unset($t['sort_at']);
                return [...$t, 'date_raw' => $sortAt->format('Y-m-d'), 'created_at' => Jalali::format($sortAt)];
            })
            ->values()
            ->all();
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'trade_room_commission_percent' => 'required|numeric|min:0|max:10',
        ]);

        $old = Setting::get('trade_room_commission_percent', 0.1);
        Setting::put('trade_room_commission_percent', $request->trade_room_commission_percent);

        $this->notifyOtherAdmins($request, 'تغییر تنظیمات توسط ادمین',
            "{$request->user()->name} کارمزد اتاق معاملاتی را از {$old}٪ به {$request->trade_room_commission_percent}٪ تغییر داد. تاریخ: " . Jalali::now());

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }

    public function setLevel(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        if ($user->id === $request->user()->id) return back();

        $level = $request->input('level', 'regular');
        match ($level) {
            'vip_admin' => $user->update(['is_vip' => true,  'is_admin' => true,  'membership_level' => 2]),
            'admin'     => $user->update(['is_vip' => false, 'is_admin' => true,  'membership_level' => 1]),
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

        $this->notifyOtherAdmins($request, 'تغییر سطح کاربری توسط ادمین',
            "{$request->user()->name} سطح کاربر «{$user->name}» را به «{$levelLabel}» تغییر داد. تاریخ: " . Jalali::now());

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

        $action = $request->amount > 0 ? 'واریز به' : 'برداشت از';
        $this->notifyOtherAdmins($request, 'تغییر کیف پول توسط ادمین',
            "{$request->user()->name} مبلغ " . number_format(abs($request->amount)) . " تومان {$action} کیف پول «{$user->name}» را ثبت کرد. تاریخ: " . Jalali::now());

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

        $this->notifyOtherAdmins($request, 'تغییر موجودی انبار توسط ادمین',
            "{$request->user()->name} " . ($grams > 0 ? 'افزایش' : 'کاهش') . " {$metalLabel} به‌مقدار " . abs($grams) . " گرم برای «{$user->name}» ثبت کرد. تاریخ: " . Jalali::now());

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

        $this->notifyOtherAdmins($request, 'ارسال اعلان توسط ادمین',
            "{$request->user()->name} اعلانی با عنوان «{$request->title}» ارسال کرد. تاریخ: " . Jalali::now());

        return back()->with('success', 'اعلان ارسال شد.');
    }

    public function updateNotification(Request $request, int $id)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'body'  => 'nullable|string',
            'type'  => 'required|in:info,trade,wallet,promo,system',
        ]);

        $notif = Notification::findOrFail($id);
        $notif->update([
            'title' => $request->title,
            'body'  => $request->body,
            'type'  => $request->type,
        ]);

        $this->notifyOtherAdmins($request, 'ویرایش اعلان توسط ادمین',
            "{$request->user()->name} اعلان «{$notif->title}» را ویرایش کرد. تاریخ: " . Jalali::now());

        return back()->with('success', 'اعلان ویرایش شد.');
    }

    public function deleteNotification(Request $request, int $id)
    {
        $notif = Notification::findOrFail($id);
        $title = $notif->title;
        $notif->delete();

        $this->notifyOtherAdmins($request, 'حذف اعلان توسط ادمین',
            "{$request->user()->name} اعلان «{$title}» را حذف کرد. تاریخ: " . Jalali::now());

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

        $this->notifyOtherAdmins($request, 'تأیید عضویت ویژه توسط ادمین',
            "{$request->user()->name} درخواست عضویت ویژه‌ی «{$user->name}» را تأیید کرد. تاریخ: " . Jalali::now());

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

        $this->notifyOtherAdmins($request, 'رد عضویت ویژه توسط ادمین',
            "{$request->user()->name} درخواست عضویت ویژه‌ی «{$user->name}» را رد کرد. تاریخ: " . Jalali::now());

        try {
            $this->sms->send($user->phone, "درخواست عضویت ویژه‌ی شما رد شد." . ($msg !== '' ? " {$msg}" : ''));
        } catch (\Exception) {}

        return back()->with('success', 'درخواست رد شد.');
    }

    public function deliveryUpdate(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:approved,shipped,delivered,rejected',
            // دلیل برای رد اجباری، برای سایر وضعیت‌ها اختیاری
            'note'   => 'required_if:status,rejected|nullable|string|max:300',
        ]);

        $delivery = SilverDeliveryRequest::with('user')->findOrFail($id);
        $note = trim((string) $request->input('note', ''));

        // در صورت رد درخواست، طلا/نقره‌ی رزرو‌شده به موجودی کاربر برمی‌گردد
        if ($request->status === 'rejected' && $delivery->status !== 'rejected') {
            if ($delivery->metal === 'gold') {
                GoldLedger::create([
                    'user_id' => $delivery->user_id, 'grams' => $delivery->grams,
                    'type' => 'delivery_refund', 'reference_type' => SilverDeliveryRequest::class, 'reference_id' => $delivery->id,
                    'description' => "بازگشت طلا — رد درخواست تحویل #{$delivery->id}",
                ]);
            } else {
                SilverLedger::create([
                    'user_id' => $delivery->user_id, 'purity' => $delivery->purity, 'grams' => $delivery->grams,
                    'type' => 'delivery_refund', 'reference_type' => SilverDeliveryRequest::class, 'reference_id' => $delivery->id,
                    'description' => "بازگشت نقره — رد درخواست تحویل #{$delivery->id}",
                ]);
            }
        }

        $delivery->update(['status' => $request->status, 'admin_note' => $note !== '' ? $note : $delivery->admin_note]);

        $statusLabel = [
            'approved'  => 'تأیید شد',
            'shipped'   => 'ارسال شد',
            'delivered' => 'تحویل داده شد',
            'rejected'  => 'رد شد',
        ][$request->status];

        $adminName = $request->user()->name;
        $body = "درخواست شما «{$statusLabel}». تاریخ: " . Jalali::now();
        if ($note !== '') $body .= "\nتوضیح ادمین: {$note}";

        Notification::create([
            'user_id' => $delivery->user_id,
            'title'   => 'وضعیت درخواست تحویل فیزیکی',
            'body'    => $body,
            'type'    => 'system',
        ]);

        $adminLog = "{$adminName} درخواست تحویل فیزیکی «{$delivery->user?->name}» را «{$statusLabel}» کرد." . ($note !== '' ? " توضیح: {$note}" : '') . ' تاریخ: ' . Jalali::now();
        $this->notifyOtherAdmins($request, 'به‌روزرسانی تحویل فیزیکی توسط ادمین', $adminLog);

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

        $oldName = $user->name;
        $user->update($request->only('name', 'phone', 'email', 'national_id'));

        $this->notifyOtherAdmins($request, 'ویرایش کاربر توسط ادمین',
            "{$request->user()->name} اطلاعات کاربر «{$oldName}» را ویرایش کرد. تاریخ: " . Jalali::now());

        return back()->with('success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function userDestroy(Request $request, int $uid)
    {
        $user = User::findOrFail($uid);
        if ($user->is_admin) {
            return back()->with('error', 'حذف حساب ادمین مجاز نیست.');
        }
        $name = $user->name;
        $user->delete();

        $this->notifyOtherAdmins($request, 'حذف کاربر توسط ادمین',
            "{$request->user()->name} کاربر «{$name}» را حذف کرد. تاریخ: " . Jalali::now());

        return back()->with('success', 'کاربر حذف شد.');
    }

    public function transactionUpdate(Request $request, int $id)
    {
        $txn = Transaction::with('user')->findOrFail($id);

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

        $this->notifyOtherAdmins($request, 'ویرایش معامله توسط ادمین',
            "{$request->user()->name} معامله‌ی «{$txn->item_label}» متعلق به «{$txn->user?->name}» را ویرایش کرد. تاریخ: " . Jalali::now());

        return back()->with('success', 'معامله ویرایش شد.');
    }

    public function transactionDestroy(Request $request, int $id)
    {
        $txn = Transaction::with('user')->findOrFail($id);
        $label = $txn->item_label;
        $userName = $txn->user?->name;
        $txn->delete();

        $this->notifyOtherAdmins($request, 'حذف معامله توسط ادمین',
            "{$request->user()->name} معامله‌ی «{$label}» متعلق به «{$userName}» را حذف کرد. تاریخ: " . Jalali::now());

        return back()->with('success', 'معامله حذف شد.');
    }

    /**
     * رد معامله‌ی فروشگاه با دلیل — اثر مالی و انباری معامله به‌طور کامل برگشت می‌خورد
     * (کیف پول و دفترکل طلا/نقره) و وضعیت معامله «رد شده» می‌شود.
     */
    public function transactionReject(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:300']);

        $txn = Transaction::with('user')->findOrFail($id);
        if (($txn->status ?? 'active') !== 'active') {
            return back()->with('error', 'این معامله قبلاً رد شده است.');
        }

        $reason = trim($request->reason);

        DB::transaction(function () use ($txn, $reason) {
            // برگشت کیف پول: خرید مبلغ را کسر کرده بود → بازگشت؛ فروش مبلغ را اضافه کرده بود → کسر
            WalletTransaction::create([
                'user_id'     => $txn->user_id,
                'amount'      => $txn->type === 'buy' ? $txn->total : -$txn->total,
                'type'        => $txn->type === 'buy' ? 'deposit' : 'withdraw',
                'description' => "برگشت — رد معامله #{$txn->id} ({$txn->item_label})",
            ]);

            // برگشت دفترکل طلا/نقره (سکه‌ها دفترکل ندارند؛ با فیلتر وضعیت در موجودی لحاظ می‌شوند)
            $mithqalGrams = (float) env('MITHQAL_GRAMS', 4.3318);
            if (in_array($txn->item, ['geram', 'mithqal'], true)) {
                $grams = $txn->item === 'mithqal' ? (float) $txn->quantity * $mithqalGrams : (float) $txn->quantity;
                GoldLedger::create([
                    'user_id' => $txn->user_id,
                    'grams'   => $txn->type === 'buy' ? -$grams : $grams,
                    'type'    => 'trade_reject',
                    'reference_type' => Transaction::class, 'reference_id' => $txn->id,
                    'description' => "برگشت طلا — رد معامله #{$txn->id}",
                ]);
            } elseif (str_contains($txn->item, '999') || str_contains($txn->item, '995')) {
                $purity = str_contains($txn->item, '995') ? '995' : '999';
                $grams  = str_starts_with($txn->item, 'mithqal_') ? (float) $txn->quantity * $mithqalGrams : (float) $txn->quantity;
                SilverLedger::create([
                    'user_id' => $txn->user_id, 'purity' => $purity,
                    'grams'   => $txn->type === 'buy' ? -$grams : $grams,
                    'type'    => 'trade_reject',
                    'reference_type' => Transaction::class, 'reference_id' => $txn->id,
                    'description' => "برگشت نقره — رد معامله #{$txn->id}",
                ]);
            }

            $txn->update(['status' => 'rejected', 'admin_note' => $reason]);

            Notification::create([
                'user_id' => $txn->user_id,
                'title'   => 'رد معامله توسط مدیریت',
                'body'    => "معامله‌ی «{$txn->item_label}» ({$txn->quantity}) رد شد و اثر آن برگشت داده شد.\nدلیل: {$reason}\nتاریخ: " . Jalali::now(),
                'type'    => 'trade',
            ]);
        });

        try {
            if ($txn->user) {
                $this->sms->send($txn->user->phone, "معامله‌ی شما ({$txn->item_label}) توسط مدیریت رد شد. دلیل: {$reason}");
            }
        } catch (\Exception) {}

        $this->notifyOtherAdmins($request, 'رد معامله‌ی فروشگاه توسط ادمین',
            "{$request->user()->name} معامله‌ی «{$txn->item_label}» متعلق به «{$txn->user?->name}» را رد کرد. دلیل: {$reason}. تاریخ: " . Jalali::now());

        return back()->with('success', 'معامله رد و برگشت داده شد.');
    }

    /**
     * رد/برگشت معامله‌ی اتاق معاملاتی با دلیل — تسویه‌ی بین دو طرف کاملاً معکوس می‌شود
     * (کیف پول و دفترکل هر دو طرف) و وضعیت پیشنهاد «لغوشده» با یادداشت ادمین می‌شود.
     */
    public function tradeRoomReject(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:300']);
        $reason = trim($request->reason);

        try {
            DB::transaction(function () use ($id, $reason) {
                $offer = TradeRoomOffer::with(['user', 'counterparty'])->where('id', $id)->lockForUpdate()->firstOrFail();
                if ($offer->status !== 'completed') {
                    throw new \RuntimeException('فقط معامله‌ی تکمیل‌شده قابل برگشت است.');
                }

                $metal   = $offer->metal;
                $purity  = $offer->purity;
                $grams   = (float) $offer->grams;
                $total   = $offer->total();
                $ownerId = $offer->user_id;
                $cpId    = $offer->counterparty_id;
                $label   = $metal === 'gold' ? 'طلا' : ('نقره ' . $purity);

                if ($offer->side === 'sell') {
                    // پیشنهاددهنده فروشنده بود؛ طرف مقابل خریدار
                    $this->revertWallet($cpId, $total, "برگشت — رد معامله‌ی اتاق #{$offer->id}");      // پول به خریدار برمی‌گردد
                    $this->revertWallet($ownerId, -$total, "برگشت — رد معامله‌ی اتاق #{$offer->id}");   // پول از فروشنده پس گرفته می‌شود
                    $this->revertLedger($cpId, $metal, $purity, -$grams, $offer->id, "برگشت {$label} — رد معامله‌ی اتاق #{$offer->id}"); // فلز از خریدار پس گرفته می‌شود
                    $this->revertLedger($ownerId, $metal, $purity, $grams, $offer->id, "برگشت {$label} — رد معامله‌ی اتاق #{$offer->id}"); // فلز رزروشده به فروشنده برمی‌گردد
                } else {
                    // پیشنهاددهنده خریدار بود (پول رزرو شده بود)؛ طرف مقابل فروشنده
                    $this->revertLedger($cpId, $metal, $purity, $grams, $offer->id, "برگشت {$label} — رد معامله‌ی اتاق #{$offer->id}");  // فلز به فروشنده برمی‌گردد
                    $this->revertWallet($cpId, -$total, "برگشت — رد معامله‌ی اتاق #{$offer->id}");      // پول از فروشنده پس گرفته می‌شود
                    $this->revertLedger($ownerId, $metal, $purity, -$grams, $offer->id, "برگشت {$label} — رد معامله‌ی اتاق #{$offer->id}"); // فلز از خریدار پس گرفته می‌شود
                    $this->revertWallet($ownerId, $total, "برگشت — رد معامله‌ی اتاق #{$offer->id}");     // پول رزروشده به خریدار برمی‌گردد
                }

                $offer->update(['status' => 'cancelled', 'admin_note' => $reason]);

                foreach (array_filter([$ownerId, $cpId]) as $uid) {
                    Notification::create([
                        'user_id' => $uid,
                        'title'   => 'برگشت معامله‌ی اتاق معاملاتی توسط مدیریت',
                        'body'    => "معامله‌ی {$label} — {$grams} گرم برگشت داده شد.\nدلیل: {$reason}\nتاریخ: " . Jalali::now(),
                        'type'    => 'trade',
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $offer = TradeRoomOffer::with(['user', 'counterparty'])->find($id);
        $this->notifyOtherAdmins($request, 'برگشت معامله‌ی اتاق معاملاتی توسط ادمین',
            "{$request->user()->name} معامله‌ی اتاق معاملاتی بین «{$offer?->user?->name}» و «{$offer?->counterparty?->name}» را برگشت زد. دلیل: {$reason}. تاریخ: " . Jalali::now());

        return back()->with('success', 'معامله‌ی اتاق معاملاتی برگشت داده شد.');
    }

    private function revertWallet(int $userId, int $amount, string $desc): void
    {
        WalletTransaction::create([
            'user_id'     => $userId,
            'amount'      => $amount,
            'type'        => $amount >= 0 ? 'deposit' : 'withdraw',
            'description' => $desc,
        ]);
    }

    private function revertLedger(int $userId, string $metal, ?string $purity, float $grams, int $offerId, string $desc): void
    {
        if ($metal === 'gold') {
            GoldLedger::create([
                'user_id' => $userId, 'grams' => $grams, 'type' => 'trade_reject',
                'reference_type' => TradeRoomOffer::class, 'reference_id' => $offerId, 'description' => $desc,
            ]);
        } else {
            SilverLedger::create([
                'user_id' => $userId, 'purity' => $purity, 'grams' => $grams, 'type' => 'trade_reject',
                'reference_type' => TradeRoomOffer::class, 'reference_id' => $offerId, 'description' => $desc,
            ]);
        }
    }

    public function withdrawalApprove(Request $request, int $id)
    {
        $request->validate(['note' => 'nullable|string|max:300']);
        $note = trim((string) $request->input('note', ''));

        $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);
        $withdrawal->update(['status' => 'approved', 'admin_note' => $note !== '' ? $note : null]);

        $body = number_format($withdrawal->amount) . ' تومان به حساب شما واریز شد. تاریخ: ' . Jalali::now();
        if ($note !== '') $body .= "\nتوضیح ادمین: {$note}";

        Notification::create([
            'user_id' => $withdrawal->user_id,
            'title'   => 'تسویه حساب انجام شد',
            'body'    => $body,
            'type'    => 'wallet',
        ]);

        $this->notifyOtherAdmins($request, 'تأیید تسویه حساب توسط ادمین',
            "{$request->user()->name} تسویه حساب " . number_format($withdrawal->amount) . " تومانی «{$withdrawal->user?->name}» را تأیید کرد." . ($note !== '' ? " توضیح: {$note}" : '') . ' تاریخ: ' . Jalali::now());

        try {
            $this->sms->send($withdrawal->user->phone, 'تسویه حساب ' . number_format($withdrawal->amount) . ' تومانی شما انجام شد.');
        } catch (\Exception) {}

        return back()->with('success', 'تسویه حساب تأیید شد.');
    }

    public function withdrawalReject(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:300']);

        $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);
        $withdrawal->update(['status' => 'rejected', 'admin_note' => $request->reason]);

        // مبلغ به کیف پول کاربر برمی‌گردد
        WalletTransaction::create([
            'user_id'     => $withdrawal->user_id,
            'amount'      => $withdrawal->amount,
            'type'        => 'deposit',
            'description' => "بازگشت — رد درخواست تسویه حساب #{$withdrawal->id}",
        ]);

        Notification::create([
            'user_id' => $withdrawal->user_id,
            'title'   => 'درخواست تسویه حساب رد شد',
            'body'    => "دلیل: {$request->reason}\nمبلغ به کیف پول شما بازگشت داده شد. تاریخ: " . Jalali::now(),
            'type'    => 'wallet',
        ]);

        $this->notifyOtherAdmins($request, 'رد تسویه حساب توسط ادمین',
            "{$request->user()->name} تسویه حساب «{$withdrawal->user?->name}» را رد کرد. دلیل: {$request->reason}. تاریخ: " . Jalali::now());

        try {
            $this->sms->send($withdrawal->user->phone, "درخواست تسویه حساب شما رد شد. دلیل: {$request->reason}");
        } catch (\Exception) {}

        return back()->with('success', 'درخواست رد شد.');
    }

    public function depositApprove(Request $request, int $id)
    {
        $request->validate(['note' => 'nullable|string|max:300']);
        $note = trim((string) $request->input('note', ''));

        $deposit = DepositRequest::with('user')->findOrFail($id);
        $deposit->update(['status' => 'approved', 'admin_note' => $note !== '' ? $note : null]);

        WalletTransaction::create([
            'user_id'     => $deposit->user_id,
            'amount'      => $deposit->amount,
            'type'        => 'deposit',
            'description' => "تأیید درخواست افزایش موجودی #{$deposit->id}",
        ]);

        $body = number_format($deposit->amount) . " تومان توسط ادمین به کیف پول شما واریز شد. تاریخ: " . Jalali::now();
        if ($note !== '') $body .= "\nتوضیح ادمین: {$note}";

        Notification::create([
            'user_id' => $deposit->user_id,
            'title'   => 'افزایش موجودی تأیید شد',
            'body'    => $body,
            'type'    => 'wallet',
        ]);

        $this->notifyOtherAdmins($request, 'تأیید افزایش موجودی توسط ادمین',
            "{$request->user()->name} افزایش موجودی " . number_format($deposit->amount) . " تومانی «{$deposit->user?->name}» را تأیید کرد." . ($note !== '' ? " توضیح: {$note}" : '') . ' تاریخ: ' . Jalali::now());

        try {
            $this->sms->send($deposit->user->phone, 'افزایش موجودی ' . number_format($deposit->amount) . ' تومانی شما تأیید و واریز شد.');
        } catch (\Exception) {}

        return back()->with('success', 'افزایش موجودی تأیید شد.');
    }

    public function depositReject(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:300']);

        $deposit = DepositRequest::with('user')->findOrFail($id);
        $deposit->update(['status' => 'rejected', 'admin_note' => $request->reason]);

        Notification::create([
            'user_id' => $deposit->user_id,
            'title'   => 'درخواست افزایش موجودی رد شد',
            'body'    => "دلیل: {$request->reason}\nتاریخ: " . Jalali::now(),
            'type'    => 'wallet',
        ]);

        $this->notifyOtherAdmins($request, 'رد افزایش موجودی توسط ادمین',
            "{$request->user()->name} درخواست افزایش موجودی «{$deposit->user?->name}» را رد کرد. دلیل: {$request->reason}. تاریخ: " . Jalali::now());

        try {
            $this->sms->send($deposit->user->phone, "درخواست افزایش موجودی شما رد شد. دلیل: {$request->reason}");
        } catch (\Exception) {}

        return back()->with('success', 'درخواست رد شد.');
    }

    /**
     * اطلاع به همه‌ی ادمین‌های دیگر (نه ادمینی که خودش این کار را انجام داده) از یک اقدام مدیریتی،
     * و ثبت همان اقدام در گزارش فعالیت (سیستم لاگ). چون همه‌ی متدهای ادمین این تابع را صدا می‌زنند،
     * هر اقدام مدیریتی به‌صورت خودکار لاگ می‌شود.
     */
    private function notifyOtherAdmins(Request $request, string $title, string $body): void
    {
        $actingId = $request->user()->id;
        User::where('is_admin', true)->where('id', '!=', $actingId)->get()
            ->each(function ($admin) use ($title, $body) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title'   => $title,
                    'body'    => $body,
                    'type'    => 'system',
                ]);
            });

        ActivityLog::record('admin_action', 'admin', "{$title} — {$body}", $actingId);
    }
}
