<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\TradeRoomOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $shopTransactions = $user->transactions()->get()->map(fn ($transaction) => [
            'id' => 'shop-'.$transaction->id,
            'source' => 'shop',
            'source_label' => 'فروشگاه',
            'type' => $transaction->type,
            'item_label' => $transaction->item_label,
            'quantity' => (float) $transaction->quantity,
            'price_per_unit' => $transaction->price_per_unit,
            'total' => $transaction->total,
            'status' => $transaction->status ?? 'active',
            'admin_note' => $transaction->admin_note,
            'created_at' => Jalali::format($transaction->created_at),
            'date_raw' => $transaction->created_at->format('Y-m-d'),
            'sort_at' => $transaction->created_at,
        ]);

        // Partial fills are separate completed records, so query completed rows rather
        // than only the original offer. The accepting party sees the opposite side.
        $roomTransactions = TradeRoomOffer::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('counterparty_id', $user->id))
            ->where(fn ($query) => $query->where('status', 'completed')
                ->orWhere(fn ($cancelled) => $cancelled->where('status', 'cancelled')->whereNotNull('admin_note')))
            ->get()
            ->map(function (TradeRoomOffer $offer) use ($user) {
                $isOfferOwner = $offer->user_id === $user->id;
                $side = $isOfferOwner
                    ? $offer->side
                    : ($offer->side === 'buy' ? 'sell' : 'buy');
                $date = $offer->completed_at ?? $offer->updated_at;

                return [
                    'id' => 'room-'.$offer->id,
                    'source' => 'room',
                    'source_label' => 'اتاق معاملاتی',
                    'is_from_bot' => $offer->source === 'telegram_bot',
                    'type' => $side,
                    'item_label' => $this->roomItemLabel($offer),
                    'quantity' => (float) $offer->grams,
                    'price_per_unit' => $offer->price_per_gram,
                    'total' => $offer->total(),
                    'status' => $offer->status === 'completed' ? 'active' : 'rejected',
                    'admin_note' => $offer->admin_note,
                    'created_at' => Jalali::format($date),
                    'date_raw' => $date->format('Y-m-d'),
                    'sort_at' => $date,
                ];
            });

        $transactions = $shopTransactions
            ->concat($roomTransactions)
            ->sortByDesc('sort_at')
            ->values()
            ->map(function (array $transaction) {
                unset($transaction['sort_at']);

                return $transaction;
            });

        return Inertia::render('History', [
            'transactions' => $transactions,
        ]);
    }

    private function roomItemLabel(TradeRoomOffer $offer): string
    {
        return match ($offer->metal) {
            'gold' => 'طلا (گرم)',
            'silver' => "نقره {$offer->purity} (گرم)",
            default => match ($offer->item) {
                'bahar' => 'سکه تمام',
                'nim' => 'نیم سکه',
                default => 'ربع سکه',
            },
        };
    }
}
