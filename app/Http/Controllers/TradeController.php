<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\Transaction;
use App\Services\PriceService;
use Illuminate\Http\Request;
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

    /** حداقل معامله برای آیتم‌های وزنی (گرم/مثقال طلا و نقره) — سکه‌ها شامل نمی‌شوند. */
    private const MIN_GRAMS = 10.0;

    public function __construct(
        private PriceService $prices,
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

        // حداقل معامله برای آیتم‌های وزنی (گرم/مثقال) — سکه‌ها (بهار/نیم/ربع) شامل نمی‌شوند
        $isWeightItem = $item === 'geram' || $item === 'mithqal' || $meta['group'] === 'silver';
        if ($isWeightItem) {
            $grams = $meta['group'] === 'gold' ? $this->goldGrams($item, $qty) : $this->silverGrams($item, $qty)[1];
            if ($grams < self::MIN_GRAMS) {
                return back()->withErrors(['quantity' => 'حداقل مقدار معامله ۱۰ گرم است.']);
            }
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

        CartItem::create([
            'user_id' => $user->id,
            'trade_type' => $request->trade_type,
            'item' => $item,
            'item_label' => $meta['label'],
            'item_group' => $meta['group'],
            'quantity' => $qty,
            'price_per_unit' => (int) $price,
            'total' => $total,
        ]);

        ActivityLog::record('cart_add', 'trade',
            "افزودن {$typeLabel} {$meta['label']} به سبد خرید — مقدار: {$qty} — مبلغ: " . number_format($total) . " تومان — کاربر: {$user->name}", $user->id);

        return redirect()->route('cart')->with('success', "{$typeLabel} به سبد خرید اضافه شد.");
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
        $base   = Transaction::where('user_id', $userId)->where('item', $item)->where('status', 'active');
        $bought = (float) (clone $base)->where('type', 'buy')->sum('quantity');
        $sold   = (float) (clone $base)->where('type', 'sell')->sum('quantity');
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
