<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $txns  = $user->transactions()->get()->map(fn($t) => [
            'id'             => $t->id,
            'type'           => $t->type,
            'item_label'     => $t->item_label,
            'quantity'       => (float) $t->quantity,
            'price_per_unit' => $t->price_per_unit,
            'total'          => $t->total,
            'created_at'     => Jalali::format($t->created_at),
            'date_raw'       => $t->created_at->format('Y-m-d'),
        ]);

        $summary = $this->buildSummary($user->transactions()->get());

        return Inertia::render('History', [
            'transactions' => $txns,
            'summary'      => $summary,
        ]);
    }

    private function buildSummary($transactions): array
    {
        $groups = [];
        foreach ($transactions as $t) {
            $label = $t->item_label;
            if (!isset($groups[$label])) {
                $groups[$label] = ['label'=>$label,'buy_qty'=>0,'sell_qty'=>0,'buy_total'=>0,'sell_total'=>0];
            }
            if ($t->type === 'buy') {
                $groups[$label]['buy_qty']   += (float) $t->quantity;
                $groups[$label]['buy_total'] += $t->total;
            } else {
                $groups[$label]['sell_qty']   += (float) $t->quantity;
                $groups[$label]['sell_total'] += $t->total;
            }
        }

        return array_values(array_map(function ($g) {
            $g['weight_balance'] = round($g['buy_qty'] - $g['sell_qty'], 4);
            $g['money_balance']  = $g['buy_total'] - $g['sell_total'];
            return $g;
        }, $groups));
    }
}
