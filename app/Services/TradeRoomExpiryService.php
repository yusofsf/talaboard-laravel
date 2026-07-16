<?php

namespace App\Services;

use App\Models\GoldLedger;
use App\Models\SilverLedger;
use App\Models\TradeRoomOffer;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TradeRoomExpiryService
{
    public function expiresAt(CarbonInterface $createdAt): CarbonInterface
    {
        $expiresAt = $createdAt->copy();

        if ($createdAt->isThursday() || $createdAt->isFriday() || $createdAt->isSaturday()) {
            $daysToSaturday = (CarbonInterface::SATURDAY - $createdAt->dayOfWeek + 7) % 7;

            return $expiresAt->addDays($daysToSaturday)->endOfDay();
        }

        return $expiresAt->endOfDay();
    }

    public function isExpired(TradeRoomOffer $offer, ?CarbonInterface $now = null): bool
    {
        return ($now ?? now())->greaterThan($this->expiresAt($offer->created_at));
    }

    public function thursdayNotice(?CarbonInterface $now = null): ?string
    {
        if (($now ?? now())->isThursday()) {
            return 'سفارش شما ثبت شد. توجه: معاملات ثبت‌شده در پنج‌شنبه، شنبه‌ای محسوب می‌شوند و تا پایان شنبه باز می‌مانند.';
        }

        return null;
    }

    public function expireOpenOffers(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $expired = 0;

        TradeRoomOffer::where('status', 'open')
            ->orderBy('id')
            ->chunkById(100, function ($offers) use ($now, &$expired) {
                foreach ($offers as $offer) {
                    if (! $this->isExpired($offer, $now)) {
                        continue;
                    }

                    DB::transaction(function () use ($offer, &$expired) {
                        $freshOffer = TradeRoomOffer::where('id', $offer->id)->lockForUpdate()->first();

                        if (! $freshOffer || $freshOffer->status !== 'open' || ! $this->isExpired($freshOffer)) {
                            return;
                        }

                        $this->refundOpenOffer($freshOffer, 'انقضای خودکار سفارش اتاق معاملاتی');
                        $freshOffer->update(['status' => 'cancelled']);
                        $expired++;
                    });
                }
            });

        return $expired;
    }

    public function expireOfferIfNeeded(TradeRoomOffer $offer): bool
    {
        if ($offer->status !== 'open' || ! $this->isExpired($offer)) {
            return false;
        }

        $this->refundOpenOffer($offer, 'انقضای خودکار سفارش اتاق معاملاتی');
        $offer->update(['status' => 'cancelled']);

        return true;
    }

    private function refundOpenOffer(TradeRoomOffer $offer, string $reason): void
    {
        if ($offer->side === 'sell') {
            if ($offer->metal === 'gold') {
                GoldLedger::create([
                    'user_id' => $offer->user_id,
                    'grams' => (float) $offer->grams,
                    'type' => 'offer_refund',
                    'reference_type' => TradeRoomOffer::class,
                    'reference_id' => $offer->id,
                    'description' => "{$reason} #{$offer->id}",
                ]);
            } elseif ($offer->metal === 'silver') {
                SilverLedger::create([
                    'user_id' => $offer->user_id,
                    'purity' => $offer->purity,
                    'grams' => (float) $offer->grams,
                    'type' => 'offer_refund',
                    'reference_type' => TradeRoomOffer::class,
                    'reference_id' => $offer->id,
                    'description' => "{$reason} #{$offer->id}",
                ]);
            }

            return;
        }

        WalletTransaction::create([
            'user_id' => $offer->user_id,
            'amount' => $offer->total(),
            'type' => 'deposit',
            'description' => "{$reason} #{$offer->id}",
        ]);
    }
}
