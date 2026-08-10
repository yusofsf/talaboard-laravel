<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\BankCard;
use App\Models\DepositRequest;
use App\Models\Notification;
use App\Models\TradeRoomOffer;
use App\Models\Transaction;
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
        $txns = $user->walletTransactions()->get()->map(fn ($t) => [
            'id' => $t->id,
            'amount' => $t->amount,
            'type' => $t->type,
            'description' => $t->description,
            'created_at' => Jalali::format($t->created_at),
        ]);

        $withdrawals = WithdrawalRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'amount' => $w->amount,
                'card_number' => $w->card_number,
                'shaba' => $w->shaba,
                'status' => $w->status,
                'admin_note' => $w->admin_note,
                'created_at' => Jalali::format($w->created_at),
            ]);

        $deposits = DepositRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'amount' => $d->amount,
                'tracking_number' => $d->tracking_number,
                'note' => $d->note,
                'receipt_url' => $d->receipt_path ? asset('storage/'.$d->receipt_path) : null,
                'status' => $d->status,
                'admin_note' => $d->admin_note,
                'created_at' => Jalali::format($d->created_at),
            ]);

        $bankCards = $user->bankCards()->get(['id', 'bank_name', 'card_number', 'shaba']);

        return Inertia::render('Wallet', [
            'balance' => $user->walletBalance(),
            'txns' => $txns,
            'withdrawals' => $withdrawals,
            'deposits' => $deposits,
            'bankCards' => $bankCards,
            'depositAccount' => [
                'account_number' => config('deposit.account_number'),
                'iban' => config('deposit.iban'),
                'account_holder' => config('deposit.account_holder'),
            ],
        ]);
    }

    /** A read-only, member-scoped view of the three append-only ledgers. */
    public function accounting(Request $request)
    {
        $user = $request->user();

        $cashTransactions = $user->walletTransactions()->get()->map(fn ($t) => [
            'id' => $t->id,
            'amount' => (int) $t->amount,
            'type' => $t->type,
            'description' => $t->description,
            'created_at' => Jalali::format($t->created_at),
            'date_raw' => $t->created_at->format('Y-m-d'),
        ]);

        $assetTransactions = $user->goldLedger()->get()->map(fn ($t) => [
            'id' => 'gold-'.$t->id,
            'asset' => 'طلا',
            'grams' => (float) $t->grams,
            'type' => $t->type,
            'description' => $t->description,
            'created_at' => Jalali::format($t->created_at),
            'date_raw' => $t->created_at->format('Y-m-d'),
            'sort_at' => $t->created_at,
        ])->merge($user->silverLedger()->get()->map(fn ($t) => [
            'id' => 'silver-'.$t->id,
            'asset' => 'نقره '.$t->purity,
            'grams' => (float) $t->grams,
            'type' => $t->type,
            'description' => $t->description,
            'created_at' => Jalali::format($t->created_at),
            'date_raw' => $t->created_at->format('Y-m-d'),
            'sort_at' => $t->created_at,
        ]))->sortByDesc('sort_at')->values()->map(function (array $entry) {
            unset($entry['sort_at']);

            return $entry;
        });

        return Inertia::render('Accounting', [
            'balances' => [
                'cash' => $user->walletBalance(),
                'gold' => $user->goldBalance(),
                'silver_999' => $user->silverBalance('999'),
                'silver_995' => $user->silverBalance('995'),
            ],
            'cashTransactions' => $cashTransactions,
            'assetTransactions' => $assetTransactions,
            'tradeSummary' => $this->tradeSummary($user),
        ]);
    }

    private function tradeSummary(User $user): array
    {
        $trades = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->map(fn (Transaction $trade) => [
                'label' => $trade->item_label,
                'side' => $trade->type,
                'quantity' => (float) $trade->quantity,
                'total' => $trade->total,
            ])
            ->concat(TradeRoomOffer::query()
                ->where('status', 'completed')
                ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('counterparty_id', $user->id))
                ->get()
                ->map(function (TradeRoomOffer $trade) use ($user) {
                    $side = $trade->user_id === $user->id
                        ? $trade->side
                        : ($trade->side === 'buy' ? 'sell' : 'buy');

                    return [
                        'label' => match ($trade->metal) {
                            'gold' => 'طلا (گرم)',
                            'silver' => "نقره {$trade->purity} (گرم)",
                            default => match ($trade->item) {
                                'bahar' => 'سکه تمام',
                                'nim' => 'نیم سکه',
                                default => 'ربع سکه',
                            },
                        },
                        'side' => $side,
                        'quantity' => (float) $trade->grams,
                        'total' => $trade->total(),
                    ];
                }));

        $groups = [];
        foreach ($trades as $trade) {
            $label = $trade['label'];
            $groups[$label] ??= ['label' => $label, 'buy_qty' => 0, 'sell_qty' => 0, 'buy_total' => 0, 'sell_total' => 0];

            if ($trade['side'] === 'buy') {
                $groups[$label]['buy_qty'] += $trade['quantity'];
                $groups[$label]['buy_total'] += $trade['total'];
            } else {
                $groups[$label]['sell_qty'] += $trade['quantity'];
                $groups[$label]['sell_total'] += $trade['total'];
            }
        }

        return array_values(array_map(function (array $group) {
            $group['weight_balance'] = round($group['buy_qty'] - $group['sell_qty'], 4);
            $group['money_balance'] = $group['buy_total'] - $group['sell_total'];

            return $group;
        }, $groups));
    }

    /** درخواست افزایش موجودی پس از واریز بانکی و ارسال تصویر فیش؛ اعتباردهی فقط پس از تأیید ادمین انجام می‌شود. */
    public function requestDeposit(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'amount' => 'required|integer|min:1000',
            'receipt' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ], [
            'receipt.required' => 'تصویر فیش واریزی را انتخاب کنید.',
            'receipt.image' => 'فایل فیش باید یک تصویر معتبر باشد.',
            'receipt.mimes' => 'فرمت تصویر فیش باید JPEG، PNG یا WebP باشد.',
            'receipt.max' => 'حجم تصویر فیش نباید بیشتر از ۵ مگابایت باشد.',
        ]);

        $admins = User::where('is_admin', true)->get();
        $receiptPath = $request->file('receipt')->store('receipts/deposits', 'public');

        $deposit = DepositRequest::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'receipt_path' => $receiptPath,
            'source' => 'website',
            'status' => 'pending',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'درخواست افزایش موجودی ثبت شد',
            'body' => number_format($request->amount).' تومان — تاریخ: '.Jalali::now().' — در حال بررسی.',
            'type' => 'wallet',
        ]);

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => "درخواست افزایش موجودی — {$user->name}",
                'body' => number_format($request->amount).' تومان — تاریخ: '.Jalali::now(),
                'type' => 'wallet',
            ]);
        }

        ActivityLog::record('deposit_request', 'wallet',
            'درخواست افزایش موجودی '.number_format($request->amount)." تومان — کاربر: {$user->name}", $user->id);

        try {
            $this->sms->send($user->phone, 'درخواست افزایش موجودی '.number_format($request->amount).' تومانی شما ثبت شد و در حال بررسی است.');
        } catch (\Exception) {
        }

        return back()->with('success', 'درخواست افزایش موجودی شما ثبت شد و پس از تأیید ادمین به کیف پول شما اضافه می‌شود.');
    }

    public function requestWithdrawal(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'amount' => 'required|integer|min:1000',
            'bank_card_id' => 'required|exists:bank_cards,id',
        ]);

        $card = BankCard::where('user_id', $user->id)->where('id', $request->bank_card_id)->first();
        if (! $card) {
            return back()->withErrors(['bank_card_id' => 'کارت بانکی انتخاب‌شده معتبر نیست.']);
        }

        if ($user->walletBalance() <= 0) {
            return back()->withErrors(['amount' => 'موجودی کیف پول شما صفر است.']);
        }
        if ($user->walletBalance() < $request->amount) {
            return back()->withErrors(['amount' => 'موجودی کیف پول شما کافی نیست.']);
        }

        $admins = User::where('is_admin', true)->get();

        DB::transaction(function () use ($user, $request, $admins, $card) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($lockedUser->walletBalance() < (int) $request->amount, 422, 'موجودی کیف پول شما کافی نیست.');

            $withdrawal = WithdrawalRequest::create([
                'user_id' => $lockedUser->id,
                'amount' => $request->amount,
                'card_number' => $card->card_number,
                'shaba' => $card->shaba,
                'status' => 'pending',
            ]);

            WalletTransaction::create([
                'user_id' => $lockedUser->id,
                'amount' => -$request->amount,
                'type' => 'withdraw',
                'description' => "درخواست تسویه حساب #{$withdrawal->id}",
            ]);

            Notification::create([
                'user_id' => $lockedUser->id,
                'title' => 'درخواست تسویه حساب ثبت شد',
                'body' => number_format($request->amount).' تومان — تاریخ: '.Jalali::now().' — در حال بررسی.',
                'type' => 'wallet',
            ]);

            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title' => "درخواست تسویه حساب — {$user->name}",
                    'body' => number_format($request->amount).' تومان — تاریخ: '.Jalali::now(),
                    'type' => 'wallet',
                ]);
            }
        });

        ActivityLog::record('withdrawal_request', 'wallet',
            'درخواست تسویه حساب '.number_format($request->amount)." تومان — کاربر: {$user->name}", $user->id);

        try {
            $this->sms->send($user->phone, 'درخواست تسویه حساب '.number_format($request->amount).' تومانی شما ثبت شد و در حال بررسی است.');
            foreach ($admins as $admin) {
                $this->sms->send($admin->phone, "درخواست تسویه حساب جدید: {$user->name} — ".number_format($request->amount).' تومان.');
            }
        } catch (\Exception) {
        }

        return back()->with('success', 'درخواست تسویه حساب شما ثبت شد.');
    }
}
