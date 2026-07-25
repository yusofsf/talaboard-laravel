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
            'summary' => $this->buildSummary($transactions->where('status', 'active')),
            'wallet_balance' => $user->walletBalance(),
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

    private function buildSummary($transactions): array
    {
        $groups = [];
        foreach ($transactions as $transaction) {
            $label = $transaction['item_label'];
            if (! isset($groups[$label])) {
                $groups[$label] = ['label' => $label, 'buy_qty' => 0, 'sell_qty' => 0, 'buy_total' => 0, 'sell_total' => 0];
            }
            if ($transaction['type'] === 'buy') {
                $groups[$label]['buy_qty'] += (float) $transaction['quantity'];
                $groups[$label]['buy_total'] += $transaction['total'];
            } else {
                $groups[$label]['sell_qty'] += (float) $transaction['quantity'];
                $groups[$label]['sell_total'] += $transaction['total'];
            }
        }

        return array_values(array_map(function ($group) {
            $group['weight_balance'] = round($group['buy_qty'] - $group['sell_qty'], 4);
            $group['money_balance'] = $group['buy_total'] - $group['sell_total'];

            return $group;
        }, $groups));
    }
}
