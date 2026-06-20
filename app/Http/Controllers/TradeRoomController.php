<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Notification;
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

        $offers = TradeRoomOffer::with(['user', 'counterparty'])
            ->where('status', 'open')
            ->orderByDesc('created_at')
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
            'offers'        => $offers,
            'myOffers'      => $myOffers,
            'silverBalance' => [
                '999' => $request->user()->silverBalance('999'),
                '995' => $request->user()->silverBalance('995'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensureVip($user);

        $request->validate([
            'side'           => 'required|in:buy,sell',
            'purity'         => 'required|in:999,995',
            'grams'          => 'required|numeric|min:0.1',
            'price_per_gram' => 'required|integer|min:1',
        ]);

        $grams = (float) $request->grams;
        $total = (int) round($grams * $request->price_per_gram);

        if ($request->side === 'sell' && $user->silverBalance($request->purity) < $grams) {
            return back()->withErrors(['grams' => 'موجودی نقره‌ی شما برای این عیار کافی نیست.']);
        }
        if ($request->side === 'buy' && $user->walletBalance() < $total) {
            return back()->withErrors(['grams' => 'موجودی کیف پول شما برای این مبلغ کافی نیست.']);
        }

        DB::transaction(function () use ($user, $request, $grams, $total) {
            $offer = TradeRoomOffer::create([
                'user_id'        => $user->id,
                'side'           => $request->side,
                'purity'         => $request->purity,
                'grams'          => $grams,
                'price_per_gram' => $request->price_per_gram,
                'status'         => 'open',
            ]);

            // رزرو (escrow) دارایی پیشنهاددهنده تا زمان تطبیق یا لغو
            if ($request->side === 'sell') {
                SilverLedger::create([
                    'user_id' => $user->id, 'purity' => $request->purity, 'grams' => -$grams,
                    'type' => 'offer_escrow', 'reference_type' => TradeRoomOffer::class, 'reference_id' => $offer->id,
                    'description' => "رزرو برای پیشنهاد فروش #{$offer->id}",
                ]);
            } else {
                WalletTransaction::create([
                    'user_id' => $user->id, 'amount' => -$total, 'type' => 'withdraw',
                    'description' => "رزرو برای پیشنهاد خرید #{$offer->id}",
                ]);
            }
        });

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

                $grams = (float) $offer->grams;
                $total = $offer->total();

                if ($offer->side === 'sell') {
                    // پیشنهاددهنده فروشنده است؛ acceptor خریدار است
                    if ($acceptor->walletBalance() < $total) {
                        throw new \RuntimeException('موجودی کیف پول شما کافی نیست.');
                    }
                    WalletTransaction::create(['user_id' => $acceptor->id, 'amount' => -$total, 'type' => 'withdraw', 'description' => "خرید نقره از اتاق معاملاتی #{$offer->id}"]);
                    WalletTransaction::create(['user_id' => $offer->user_id, 'amount' => $total, 'type' => 'deposit', 'description' => "فروش نقره در اتاق معاملاتی #{$offer->id}"]);
                    SilverLedger::create(['user_id' => $acceptor->id, 'purity' => $offer->purity, 'grams' => $grams, 'type' => 'p2p_buy', 'reference_type' => TradeRoomOffer::class, 'reference_id' => $offer->id, 'description' => "خرید از اتاق معاملاتی #{$offer->id}"]);
                } else {
                    // پیشنهاددهنده خریدار است (پول قبلاً رزرو شده)؛ acceptor فروشنده است
                    if ($acceptor->silverBalance($offer->purity) < $grams) {
                        throw new \RuntimeException('موجودی نقره‌ی شما کافی نیست.');
                    }
                    SilverLedger::create(['user_id' => $acceptor->id, 'purity' => $offer->purity, 'grams' => -$grams, 'type' => 'p2p_sell', 'reference_type' => TradeRoomOffer::class, 'reference_id' => $offer->id, 'description' => "فروش به اتاق معاملاتی #{$offer->id}"]);
                    WalletTransaction::create(['user_id' => $acceptor->id, 'amount' => $total, 'type' => 'deposit', 'description' => "فروش نقره در اتاق معاملاتی #{$offer->id}"]);
                    SilverLedger::create(['user_id' => $offer->user_id, 'purity' => $offer->purity, 'grams' => $grams, 'type' => 'p2p_buy', 'reference_type' => TradeRoomOffer::class, 'reference_id' => $offer->id, 'description' => "خرید از اتاق معاملاتی #{$offer->id}"]);
                }

                $offer->update(['status' => 'completed', 'counterparty_id' => $acceptor->id, 'completed_at' => now()]);

                $label = self::PURITY_LABEL[$offer->purity];
                foreach ([$offer->user_id, $acceptor->id] as $uid) {
                    Notification::create([
                        'user_id' => $uid,
                        'title'   => 'معامله‌ی اتاق معاملاتی تکمیل شد',
                        'body'    => "{$label} — {$grams} گرم — " . number_format($total) . ' تومان — ' . Jalali::now(),
                        'type'    => 'trade',
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

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
                    SilverLedger::create(['user_id' => $user->id, 'purity' => $offer->purity, 'grams' => $offer->grams, 'type' => 'offer_refund', 'reference_type' => TradeRoomOffer::class, 'reference_id' => $offer->id, 'description' => "بازگشت رزرو پیشنهاد لغوشده #{$offer->id}"]);
                } else {
                    WalletTransaction::create(['user_id' => $user->id, 'amount' => $offer->total(), 'type' => 'deposit', 'description' => "بازگشت رزرو پیشنهاد لغوشده #{$offer->id}"]);
                }

                $offer->update(['status' => 'cancelled']);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return back()->with('success', 'پیشنهاد لغو شد.');
    }

    private function present(TradeRoomOffer $o, User $viewer): array
    {
        return [
            'id'             => $o->id,
            'side'           => $o->side,
            'purity'         => $o->purity,
            'purity_label'   => self::PURITY_LABEL[$o->purity],
            'grams'          => (float) $o->grams,
            'price_per_gram' => $o->price_per_gram,
            'total'          => $o->total(),
            'status'         => $o->status,
            'is_mine'        => $o->user_id === $viewer->id,
            'user_name'      => $o->user?->name,
            'counterparty_name' => $o->counterparty?->name,
            'created_at'     => Jalali::format($o->created_at),
            'completed_at'   => $o->completed_at ? Jalali::format($o->completed_at) : null,
        ];
    }

    private function ensureVip(User $user): void
    {
        if (!$user->isVipMember()) {
            abort(403, 'اتاق معاملاتی فقط برای اعضای ویژه است.');
        }
    }
}
