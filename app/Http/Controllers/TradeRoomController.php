<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\SilverLedger;
use App\Models\TradeRoomOffer;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TradeRoomController extends Controller
{
    private const PURITY_LABEL = ['999' => 'نقره ۹۹۹/۹', '995' => 'نقره ۹۹۵'];

    public function index(Request $request)
    {
        $this->ensureVip($request->user());

        // سفارش‌های فروش: ارزان‌ترین اول (برای خریدار بهترین قیمت بالاست)
        $sellOffers = TradeRoomOffer::with(['user', 'counterparty'])
            ->where('status', 'open')->where('side', 'sell')
            ->orderBy('price_per_gram')->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => $this->present($o, $request->user()));

        // سفارش‌های خرید: گران‌ترین اول (برای فروشنده بهترین قیمت بالاست)
        $buyOffers = TradeRoomOffer::with(['user', 'counterparty'])
            ->where('status', 'open')->where('side', 'buy')
            ->orderByDesc('price_per_gram')->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => $this->present($o, $request->user()));

        $myOffers = TradeRoomOffer::with(['user', 'counterparty'])
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'open')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($o) => $this->present($o, $request->user()));

        return Inertia::render('TradeRoom', [
            'sellOffers'    => $sellOffers,
            'buyOffers'     => $buyOffers,
            'myOffers'      => $myOffers,
            'walletBalance' => $request->user()->walletBalance(),
            'goldBalance'   => $request->user()->goldBalance(),
            'silverBalance' => [
                '999' => $request->user()->silverBalance('999'),
                '995' => $request->user()->silverBalance('995'),
            ],
            'commissionPercent' => (float) Setting::get('trade_room_commission_percent', 0.1),
            'mithqalGrams'      => (float) env('MITHQAL_GRAMS', 4.3318),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensureVip($user);

        $request->validate([
            'metal'          => 'required|in:gold,silver',
            'side'           => 'required|in:buy,sell',
            'purity'         => 'required_if:metal,silver|in:999,995',
            'grams'          => 'required|numeric|min:100',
            'price_per_gram' => 'required|integer|min:1',
        ], [
            'grams.min' => 'حداقل مقدار پیشنهاد در اتاق معاملاتی ۱۰۰ گرم است.',
        ]);

        $metal  = $request->metal;
        $purity = $metal === 'silver' ? $request->purity : '';
        $grams  = (float) $request->grams;
        $total  = (int) round($grams * $request->price_per_gram);

        if ($request->side === 'sell' && $this->balance($user, $metal, $purity) < $grams) {
            return back()->withErrors(['grams' => 'موجودی شما برای این مورد کافی نیست.']);
        }
        if ($request->side === 'buy' && $user->walletBalance() < $total) {
            return back()->withErrors(['grams' => 'موجودی کیف پول شما برای این مبلغ کافی نیست.']);
        }

        DB::transaction(function () use ($user, $request, $metal, $purity, $grams, $total) {
            $offer = TradeRoomOffer::create([
                'user_id'        => $user->id,
                'metal'          => $metal,
                'side'           => $request->side,
                'purity'         => $purity,
                'grams'          => $grams,
                'price_per_gram' => $request->price_per_gram,
                'status'         => 'open',
            ]);

            // رزرو (escrow) دارایی پیشنهاددهنده تا زمان تطبیق یا لغو
            if ($request->side === 'sell') {
                $this->createLedger($user->id, $metal, $purity, -$grams, 'offer_escrow', $offer->id, "رزرو برای پیشنهاد فروش #{$offer->id}");
            } else {
                WalletTransaction::create([
                    'user_id' => $user->id, 'amount' => -$total, 'type' => 'withdraw',
                    'description' => "رزرو برای پیشنهاد خرید #{$offer->id}",
                ]);
            }
        });

        $sideLabel = $request->side === 'sell' ? 'فروش' : 'خرید';
        $metalLabel = $metal === 'gold' ? 'طلا' : ('نقره ' . $purity);
        ActivityLog::record('room_offer', 'trade',
            "ثبت پیشنهاد {$sideLabel} {$metalLabel} در اتاق معاملاتی — {$grams} گرم — کاربر: {$user->name}", $user->id);

        return back()->with('success', 'پیشنهاد شما در اتاق معاملاتی ثبت شد.');
    }

    public function accept(Request $request, int $id)
    {
        $acceptor = $request->user();
        $this->ensureVip($acceptor);

        try {
            DB::transaction(function () use ($acceptor, $id) {
                $offer = TradeRoomOffer::where('id', $id)->lockForUpdate()->firstOrFail();

                if ($offer->status !== 'open') {
                    throw new \RuntimeException('این پیشنهاد دیگر باز نیست.');
                }
                if ($offer->user_id === $acceptor->id) {
                    throw new \RuntimeException('نمی‌توانید پیشنهاد خودتان را بپذیرید.');
                }

                $metal  = $offer->metal;
                $purity = $offer->purity;
                $grams  = (float) $offer->grams;
                $total  = $offer->total();
                $itemLabel = $metal === 'gold' ? 'طلا' : self::PURITY_LABEL[$purity];

                // کارمزد اتاق معاملاتی — به‌صورت نصف‌نصف بین خریدار و فروشنده. مبلغ کارمزد از سیستم خارج می‌شود (سهم فروشگاه).
                $rate      = (float) Setting::get('trade_room_commission_percent', 0.1) / 100;
                $fee       = (int) round($total * $rate);
                $buyerFee  = intdiv($fee, 2);
                $sellerFee = $fee - $buyerFee;

                if ($offer->side === 'sell') {
                    // پیشنهاددهنده فروشنده است؛ acceptor خریدار است
                    if ($acceptor->walletBalance() < $total + $buyerFee) {
                        throw new \RuntimeException('موجودی کیف پول شما کافی نیست.');
                    }
                    WalletTransaction::create(['user_id' => $acceptor->id, 'amount' => -($total + $buyerFee), 'type' => 'withdraw', 'description' => "خرید {$itemLabel} از اتاق معاملاتی #{$offer->id}" . ($buyerFee > 0 ? " (شامل کارمزد " . number_format($buyerFee) . " تومان)" : '')]);
                    WalletTransaction::create(['user_id' => $offer->user_id, 'amount' => $total - $sellerFee, 'type' => 'deposit', 'description' => "فروش {$itemLabel} در اتاق معاملاتی #{$offer->id}" . ($sellerFee > 0 ? " (پس از کسر کارمزد " . number_format($sellerFee) . " تومان)" : '')]);
                    $this->createLedger($acceptor->id, $metal, $purity, $grams, 'p2p_buy', $offer->id, "خرید از اتاق معاملاتی #{$offer->id}");
                } else {
                    // پیشنهاددهنده خریدار است (پول قبلاً رزرو شده)؛ acceptor فروشنده است
                    if ($this->balance($acceptor, $metal, $purity) < $grams) {
                        throw new \RuntimeException('موجودی شما کافی نیست.');
                    }
                    $this->createLedger($acceptor->id, $metal, $purity, -$grams, 'p2p_sell', $offer->id, "فروش به اتاق معاملاتی #{$offer->id}");
                    WalletTransaction::create(['user_id' => $acceptor->id, 'amount' => $total - $sellerFee, 'type' => 'deposit', 'description' => "فروش {$itemLabel} در اتاق معاملاتی #{$offer->id}" . ($sellerFee > 0 ? " (پس از کسر کارمزد " . number_format($sellerFee) . " تومان)" : '')]);
                    if ($buyerFee > 0) {
                        WalletTransaction::create(['user_id' => $offer->user_id, 'amount' => -$buyerFee, 'type' => 'withdraw', 'description' => "کارمزد خرید اتاق معاملاتی #{$offer->id}"]);
                    }
                    $this->createLedger($offer->user_id, $metal, $purity, $grams, 'p2p_buy', $offer->id, "خرید از اتاق معاملاتی #{$offer->id}");
                }

                $offer->update(['status' => 'completed', 'counterparty_id' => $acceptor->id, 'completed_at' => now(), 'commission' => $fee]);

                $feeNote = $fee > 0 ? ' — کارمزد: ' . number_format($fee) . ' تومان' : '';
                foreach ([$offer->user_id, $acceptor->id] as $uid) {
                    Notification::create([
                        'user_id' => $uid,
                        'title'   => 'معامله‌ی اتاق معاملاتی تکمیل شد',
                        'body'    => "{$itemLabel} — {$grams} گرم — " . number_format($total) . ' تومان' . $feeNote . ' — ' . Jalali::now(),
                        'type'    => 'trade',
                    ]);
                }

                // اطلاع به ادمین‌ها از ثبت معامله‌ی اتاق معاملاتی
                foreach (User::where('is_admin', true)->pluck('id') as $adminId) {
                    Notification::create([
                        'user_id' => $adminId,
                        'title'   => 'معامله‌ی جدید در اتاق معاملاتی',
                        'body'    => "{$itemLabel} — {$grams} گرم — " . number_format($total) . ' تومان' . $feeNote . ' — ' . Jalali::now(),
                        'type'    => 'trade',
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        ActivityLog::record('room_accept', 'trade',
            "پذیرش پیشنهاد اتاق معاملاتی #{$id} توسط {$acceptor->name}", $acceptor->id);

        return back()->with('success', 'معامله با موفقیت انجام شد.');
    }

    public function cancel(Request $request, int $id)
    {
        $user = $request->user();

        try {
            DB::transaction(function () use ($user, $id) {
                $offer = TradeRoomOffer::where('id', $id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                if ($offer->status !== 'open') {
                    throw new \RuntimeException('این پیشنهاد دیگر باز نیست.');
                }

                if ($offer->side === 'sell') {
                    $this->createLedger($user->id, $offer->metal, $offer->purity, (float) $offer->grams, 'offer_refund', $offer->id, "بازگشت رزرو پیشنهاد لغوشده #{$offer->id}");
                } else {
                    WalletTransaction::create(['user_id' => $user->id, 'amount' => $offer->total(), 'type' => 'deposit', 'description' => "بازگشت رزرو پیشنهاد لغوشده #{$offer->id}"]);
                }

                $offer->update(['status' => 'cancelled']);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        ActivityLog::record('room_cancel', 'trade', "لغو پیشنهاد اتاق معاملاتی #{$id} توسط {$user->name}", $user->id);

        return back()->with('success', 'پیشنهاد لغو شد.');
    }

    private function balance(User $user, string $metal, ?string $purity): float
    {
        return $metal === 'gold' ? $user->goldBalance() : $user->silverBalance($purity);
    }

    private function createLedger(int $userId, string $metal, ?string $purity, float $grams, string $type, int $offerId, string $description): void
    {
        if ($metal === 'gold') {
            GoldLedger::create([
                'user_id' => $userId, 'grams' => $grams, 'type' => $type,
                'reference_type' => TradeRoomOffer::class, 'reference_id' => $offerId, 'description' => $description,
            ]);
        } else {
            SilverLedger::create([
                'user_id' => $userId, 'purity' => $purity, 'grams' => $grams, 'type' => $type,
                'reference_type' => TradeRoomOffer::class, 'reference_id' => $offerId, 'description' => $description,
            ]);
        }
    }

    /** هویت طرف‌های معامله در اتاق معاملاتی نمایش داده نمی‌شود — نه در UI و نه در پاسخ سرور (برای ادمین جدا و کامل در allTradesHistory موجود است). */
    private function present(TradeRoomOffer $o, User $viewer): array
    {
        return [
            'id'             => $o->id,
            'metal'          => $o->metal,
            'side'           => $o->side,
            'purity'         => $o->purity,
            'item_label'     => $o->metal === 'gold' ? 'طلا (گرم)' : self::PURITY_LABEL[$o->purity],
            'grams'          => (float) $o->grams,
            'price_per_gram' => $o->price_per_gram,
            'total'          => $o->total(),
            'status'         => $o->status,
            'is_mine'        => $o->user_id === $viewer->id,
            'admin_note'     => $o->admin_note,
            'commission'     => (int) $o->commission,
            'created_at'     => Jalali::format($o->created_at),
            'completed_at'   => $o->completed_at ? Jalali::format($o->completed_at) : null,
            'date_raw'       => ($o->completed_at ?? $o->created_at)->format('Y-m-d'),
        ];
    }

    private function ensureVip(User $user): void
    {
        if (!$user->isVipMember()) {
            abort(403, 'اتاق معاملاتی فقط برای اعضای ویژه است.');
        }
    }
}
