<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\SilverLedger;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Services\PriceService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TradeController extends Controller
{
    private const ITEMS = [
        'mithqal'     => ['label' => 'مثقال طلا',        'group' => 'gold'],
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

        // خرید: باید موجودی کیف پول کافی باشد (به همان مبلغ کسر می‌شود)
        // فروش: کاربر باید همان مقدار طلا/نقره را در حساب خود داشته باشد
        if ($request->trade_type === 'buy' && $user->walletBalance() < $total) {
            return back()->withErrors(['quantity' => 'موجودی کیف پول شما کافی نیست. لطفاً ابتدا کیف پول خود را شارژ کنید.']);
        }

        if ($request->trade_type === 'sell') {
            if ($item === 'geram' || $item === 'mithqal') {
                $grams = $this->goldGrams($item, $qty);
                if ($user->goldBalance() < $grams) {
                    return back()->withErrors(['quantity' => 'موجودی طلای شما کافی نیست.']);
                }
            } elseif ($meta['group'] === 'gold') {
                // سکه‌ها (بهار/نیم/ربع) — موجودی بر اساس تاریخچه‌ی معاملات همان سکه
                $holding = $this->coinHolding($user->id, $item);
                if ($holding < $qty) {
                    return back()->withErrors(['quantity' => "موجودی شما از «{$meta['label']}» کافی نیست. موجودی فعلی: {$holding}"]);
                }
            } else {
                [$purity, $grams] = $this->silverGrams($item, $qty);
                if ($user->silverBalance($purity) < $grams) {
                    return back()->withErrors(['quantity' => 'موجودی نقره‌ی شما برای این عیار کافی نیست.']);
                }
            }
        }

        $typeLabel = $request->trade_type === 'buy' ? 'خرید' : 'فروش';

        DB::transaction(function () use ($user, $request, $item, $meta, $qty, $price, $total, $typeLabel) {
            Transaction::create([
                'user_id'       => $user->id,
                'type'          => $request->trade_type,
                'item'          => $item,
                'item_label'    => $meta['label'],
                'quantity'      => $qty,
                'price_per_unit'=> (int) $price,
                'total'         => $total,
            ]);

            WalletTransaction::create([
                'user_id'     => $user->id,
                'amount'      => $request->trade_type === 'buy' ? -$total : $total,
                'type'        => $request->trade_type === 'buy' ? 'withdraw' : 'deposit',
                'description' => "{$typeLabel} {$meta['label']} ({$qty})",
            ]);

            Notification::create([
                'user_id' => $user->id,
                'title'   => "ثبت {$typeLabel} — {$meta['label']}",
                'body'    => "نوع: {$typeLabel} | مقدار: {$qty} | مبلغ: " . number_format($total) . " تومان | تاریخ: " . Jalali::now(),
                'type'    => 'trade',
            ]);

            // خرید/فروش طلا (گرم یا مثقال) → موجودی انبار طلای کاربر برحسب گرم تغییر می‌کند
            if ($item === 'geram' || $item === 'mithqal') {
                $grams = $this->goldGrams($item, $qty);
                GoldLedger::create([
                    'user_id' => $user->id,
                    'grams'   => $request->trade_type === 'buy' ? $grams : -$grams,
                    'type'    => $request->trade_type === 'buy' ? 'purchase' : 'sale',
                    'description' => "{$typeLabel} از فروشگاه — {$meta['label']} ({$qty})",
                ]);
            }

            // خرید/فروش نقره (گرم یا مثقال) → موجودی انبار نقره‌ی کاربر برحسب گرم تغییر می‌کند
            if ($meta['group'] === 'silver') {
                [$purity, $grams] = $this->silverGrams($item, $qty);
                SilverLedger::create([
                    'user_id' => $user->id,
                    'purity'  => $purity,
                    'grams'   => $request->trade_type === 'buy' ? $grams : -$grams,
                    'type'    => $request->trade_type === 'buy' ? 'purchase' : 'sale',
                    'description' => "{$typeLabel} از فروشگاه — {$meta['label']} ({$qty})",
                ]);
            }
        });

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

    /** موجودی فعلی کاربر از یک سکه (مجموع خریدها منهای فروش‌ها از تاریخچه‌ی معاملات). */
    private function coinHolding(int $userId, string $item): float
    {
        $bought = (float) Transaction::where('user_id', $userId)->where('item', $item)->where('type', 'buy')->sum('quantity');
        $sold   = (float) Transaction::where('user_id', $userId)->where('item', $item)->where('type', 'sell')->sum('quantity');
        return round($bought - $sold, 4);
    }

    /** تبدیل آیتم نقره + مقدار خریداری/فروخته‌شده به [عیار, گرم]. */
    private function silverGrams(string $item, float $qty): array
    {
        $purity = str_contains($item, '995') ? '995' : '999';
        $grams  = str_starts_with($item, 'mithqal_')
            ? $qty * (float) env('MITHQAL_GRAMS', 4.3318)
            : $qty;
        return [$purity, round($grams, 4)];
    }

    /** تبدیل آیتم طلا (گرم یا مثقال) + مقدار به گرم. */
    private function goldGrams(string $item, float $qty): float
    {
        $grams = $item === 'mithqal' ? $qty * (float) env('MITHQAL_GRAMS', 4.3318) : $qty;
        return round($grams, 4);
    }
}
