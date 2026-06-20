<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Notification;
use App\Models\Transaction;
use App\Services\PriceService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TradeController extends Controller
{
    private const ITEMS = [
        'mithqal'     => ['label' => 'مثقال طلا',       'group' => 'gold'],
        'geram'       => ['label' => 'گرم طلا',          'group' => 'gold'],
        'bahar'       => ['label' => 'سکه تمام',         'group' => 'gold'],
        'nim'         => ['label' => 'نیم سکه',           'group' => 'gold'],
        'rob'         => ['label' => 'ربع سکه',           'group' => 'gold'],
        'mithqal_999' => ['label' => 'مثقال نقره ۹۹۹/۹', 'group' => 'silver'],
        'gram_999'    => ['label' => 'گرم نقره ۹۹۹/۹',   'group' => 'silver'],
        'mithqal_995' => ['label' => 'مثقال نقره ۹۹۵',   'group' => 'silver'],
        'gram_995'    => ['label' => 'گرم نقره ۹۹۵',     'group' => 'silver'],
    ];

    public function __construct(
        private PriceService $prices,
        private SmsService   $sms,
    ) {}

    public function show(string $item)
    {
        $meta = self::ITEMS[$item] ?? null;
        if (!$meta) return redirect('/');

        $data = $this->prices->all();

        return Inertia::render('Trade', [
            'item'      => $item,
            'meta'      => $meta,
            // مشتری می‌خرد → قیمت فروش ما؛ مشتری می‌فروشد → قیمت خرید ما
            'sellPrice' => $this->lookup($data, $item, $meta, 'gold', 'silver'),
            'buyPrice'  => $this->lookup($data, $item, $meta, 'gold_buy', 'silver_buy'),
        ]);
    }

    public function store(Request $request, string $item)
    {
        $meta = self::ITEMS[$item] ?? null;
        if (!$meta) return redirect('/');

        $request->validate([
            'trade_type' => 'required|in:buy,sell',
            'quantity'   => 'required|numeric|min:0.001',
        ]);

        $data  = $this->prices->all();
        $price = $request->trade_type === 'buy'
            ? $this->lookup($data, $item, $meta, 'gold', 'silver')
            : $this->lookup($data, $item, $meta, 'gold_buy', 'silver_buy');

        if (!$price) {
            return back()->withErrors(['quantity' => 'قیمت در حال حاضر در دسترس نیست.']);
        }

        $qty   = (float) $request->quantity;
        $total = (int) round($qty * $price);
        $user  = $request->user();

        Transaction::create([
            'user_id'       => $user->id,
            'type'          => $request->trade_type,
            'item'          => $item,
            'item_label'    => $meta['label'],
            'quantity'      => $qty,
            'price_per_unit'=> (int) $price,
            'total'         => $total,
        ]);

        $typeLabel = $request->trade_type === 'buy' ? 'خرید' : 'فروش';
        Notification::create([
            'user_id' => $user->id,
            'title'   => "ثبت {$typeLabel} — {$meta['label']}",
            'body'    => "نوع: {$typeLabel} | مقدار: {$qty} | مبلغ: " . number_format($total) . " تومان | تاریخ: " . Jalali::now(),
            'type'    => 'trade',
        ]);

        try {
            $this->sms->sendTradeConfirm($user->phone, $user->name, $request->trade_type, $meta['label'], $qty, $total);
        } catch (\Exception) {}

        return redirect()->route('history')->with('success', "{$typeLabel} با موفقیت ثبت شد.");
    }

    private function lookup(array $data, string $item, array $meta, string $goldKey, string $silverKey): ?float
    {
        return $meta['group'] === 'gold'
            ? ($data[$goldKey][$item] ?? null)
            : ($data[$silverKey][$item] ?? null);
    }
}
