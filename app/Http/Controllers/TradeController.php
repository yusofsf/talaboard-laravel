<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\PriceSnapshot;
use App\Models\Transaction;
use App\Services\PriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class TradeController extends Controller
{
    private const ITEMS = [
        'mithqal' => ['label' => 'مثقال طلا',        'group' => 'gold'],
        'geram' => ['label' => 'گرم طلا',          'group' => 'gold'],
        'bahar' => ['label' => 'سکه تمام',         'group' => 'gold'],
        'nim' => ['label' => 'نیم سکه',           'group' => 'gold'],
        'rob' => ['label' => 'ربع سکه',           'group' => 'gold'],
        'mithqal_999' => ['label' => 'مثقال نقره ۹۹۹/۹', 'group' => 'silver'],
        'gram_999' => ['label' => 'گرم نقره ۹۹۹/۹',   'group' => 'silver'],
        'mithqal_995' => ['label' => 'مثقال نقره ۹۹۵',   'group' => 'silver'],
        'gram_995' => ['label' => 'گرم نقره ۹۹۵',     'group' => 'silver'],
    ];

    /** حداقل معامله برای آیتم‌های وزنی (گرم/مثقال طلا و نقره) — سکه‌ها شامل نمی‌شوند. */
    private const MIN_GRAMS = 10.0;

    public function __construct(
        private PriceService $prices,
    ) {}

    public function show(string $item)
    {
        $meta = self::ITEMS[$item] ?? null;
        if (! $meta) {
            return redirect('/');
        }

        // نمایش صفحه نباید منتظر APIها و scrapeهای بیرونی بماند. قیمت قطعی هنگام
        // ثبت سفارش در store() همچنان به‌صورت زنده دریافت می‌شود.
        $data = $this->displayPrices();

        return Inertia::render('Trade', [
            'item' => $item,
            'meta' => $meta,
            'seo' => $this->tradeSeo($item, $meta),
            // مشتری می‌خرد → قیمت فروش ما؛ مشتری می‌فروشد → قیمت خرید ما
            'sellPrice' => $this->lookup($data, $item, $meta, 'gold', 'silver'),
            'buyPrice' => $this->lookup($data, $item, $meta, 'gold_buy', 'silver_buy'),
            // برای حالت «خرید بر اساس مبلغ» (تبدیل کل مبلغ به مقدار) در فرم صفحه
            'mithqalGrams' => (float) env('MITHQAL_GRAMS', 4.3318),
            'catalog' => $this->catalog(),
        ]);
    }

    public function store(Request $request, string $item)
    {
        $meta = self::ITEMS[$item] ?? null;
        if (! $meta) {
            return redirect('/');
        }

        // دو حالت ورود: «بر اساس مقدار» (پیش‌فرض — سازگار با قبل) و «بر اساس مبلغ» (تبدیل کل مبلغ به مقدار)
        $mode = $request->input('mode', 'quantity');
        if (! in_array($mode, ['quantity', 'money'], true)) {
            $mode = 'quantity';
        }

        $rules = ['trade_type' => 'required|in:buy,sell'];
        if ($mode === 'money') {
            $rules['amount'] = 'required|numeric|min:1';
        } else {
            $rules['quantity'] = 'required|numeric|min:0.001';
        }
        $request->validate($rules);

        $data = $this->prices->all();
        $price = $request->trade_type === 'buy'
            ? $this->lookup($data, $item, $meta, 'gold', 'silver')
            : $this->lookup($data, $item, $meta, 'gold_buy', 'silver_buy');

        $errorKey = $mode === 'money' ? 'amount' : 'quantity';

        if (! $price) {
            return back()->withErrors([$errorKey => 'قیمت در حال حاضر در دسترس نیست.']);
        }

        // حداقل معامله برای آیتم‌های وزنی (گرم/مثقال) — سکه‌ها (بهار/نیم/ربع) شامل نمی‌شوند
        $isWeightItem = $item === 'geram' || $item === 'mithqal' || $meta['group'] === 'silver';

        if ($mode === 'money') {
            $amount = (float) $request->input('amount');

            if ($isWeightItem) {
                // مقدار = مبلغ ÷ قیمت؛ رو به پایین با ۴ رقم اعشار تا مبلغ پرداختی از مبلغ واردشده بیشتر نشود
                $qty = floor(($amount / $price) * 10000 + 1e-9) / 10000;
            } else {
                // سکه‌ها شمارشی‌اند — فقط تعداد صحیح
                $qty = (float) floor($amount / $price + 1e-9);
                if ($qty < 1) {
                    return back()->withErrors(['amount' => "مبلغ واردشده برای خرید حداقل یک «{$meta['label']}» کافی نیست."]);
                }
            }
        } else {
            $qty = (float) $request->quantity;
        }

        $total = (int) round($qty * $price);
        $user = $request->user();

        if ($isWeightItem) {
            $grams = $meta['group'] === 'gold' ? $this->goldGrams($item, $qty) : $this->silverGrams($item, $qty)[1];
            if ($grams < self::MIN_GRAMS) {
                $message = $mode === 'money'
                    ? 'مبلغ واردشده کمتر از حداقل معامله (۱۰ گرم) است.'
                    : 'حداقل مقدار معامله ۱۰ گرم است.';

                return back()->withErrors([$errorKey => $message]);
            }
        }

        if ($request->trade_type === 'sell') {
            if ($item === 'geram' || $item === 'mithqal') {
                $grams = $this->goldGrams($item, $qty);
                if ($user->goldBalance() < $grams) {
                    return back()->withErrors([$errorKey => 'موجودی طلای شما کافی نیست.']);
                }
            } elseif ($meta['group'] === 'gold') {
                // سکه‌ها (بهار/نیم/ربع) — موجودی بر اساس تاریخچه‌ی معاملات همان سکه
                $holding = $this->coinHolding($user->id, $item);
                if ($holding < $qty) {
                    return back()->withErrors([$errorKey => "موجودی شما از «{$meta['label']}» کافی نیست. موجودی فعلی: {$holding}"]);
                }
            } else {
                [$purity, $grams] = $this->silverGrams($item, $qty);
                if ($user->silverBalance($purity) < $grams) {
                    return back()->withErrors([$errorKey => 'موجودی نقره‌ی شما برای این عیار کافی نیست.']);
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
            "افزودن {$typeLabel} {$meta['label']} به سبد خرید — مقدار: {$qty} — مبلغ: ".number_format($total)." تومان — کاربر: {$user->name}", $user->id);

        return redirect()->route('cart')->with('success', "{$typeLabel} به سبد خرید اضافه شد.");
    }

    private function lookup(array $data, string $item, array $meta, string $goldKey, string $silverKey): ?float
    {
        return $meta['group'] === 'gold'
            ? ($data[$goldKey][$item] ?? null)
            : ($data[$silverKey][$item] ?? null);
    }

    /** فهرست گروه‌بندی‌شدهٔ محصولات برای انتخابگر «نوع خرید» در صفحهٔ معامله. */
    private function catalog(): array
    {
        $sections = [
            ['key' => 'gold',       'label' => 'طلا',        'items' => ['geram', 'mithqal']],
            ['key' => 'coin',       'label' => 'سکه',        'items' => ['bahar', 'nim', 'rob']],
            ['key' => 'silver_999', 'label' => 'نقره ۹۹۹/۹', 'items' => ['gram_999', 'mithqal_999']],
            ['key' => 'silver_995', 'label' => 'نقره ۹۹۵',   'items' => ['gram_995', 'mithqal_995']],
        ];

        $catalog = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $key) {
                if (! isset(self::ITEMS[$key])) {
                    continue;
                }
                $catalog[] = [
                    'key' => $key,
                    'label' => self::ITEMS[$key]['label'],
                    'group' => self::ITEMS[$key]['group'],
                    'section' => $section['key'],
                    'section_label' => $section['label'],
                ];
            }
        }

        return $catalog;
    }

    private function displayPrices(): array
    {
        if (Schema::hasTable('price_snapshots')) {
            $snapshot = PriceSnapshot::latestPayload();

            if ($snapshot !== null) {
                return $snapshot;
            }
        }

        return $this->prices->all();
    }

    private function tradeSeo(string $item, array $meta): array
    {
        $siteUrl = rtrim(config('seo.url'), '/');
        $siteName = config('seo.site_name');
        $label = $meta['label'];
        $canonical = "{$siteUrl}/trade/{$item}";

        return [
            'title' => "{$label} | خرید و فروش آنلاین | {$siteName}",
            'description' => "مشاهده قیمت لحظه‌ای {$label} و ثبت خرید یا فروش آنلاین در {$siteName}. قیمت‌ها به‌روز هستند و معامله پس از ورود به حساب کاربری انجام می‌شود.",
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large',
            'type' => 'product',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $label,
                'description' => "قیمت لحظه‌ای و امکان خرید و فروش آنلاین {$label}",
                'url' => $canonical,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $siteName,
                ],
            ],
        ];
    }

    /** موجودی فعلی کاربر از یک سکه (مجموع خریدها منهای فروش‌ها از تاریخچه‌ی معاملات). */
    private function coinHolding(int $userId, string $item): float
    {
        $base = Transaction::where('user_id', $userId)->where('item', $item)->where('status', 'active');
        $bought = (float) (clone $base)->where('type', 'buy')->sum('quantity');
        $sold = (float) (clone $base)->where('type', 'sell')->sum('quantity');

        return round($bought - $sold, 4);
    }

    /** تبدیل آیتم نقره + مقدار خریداری/فروخته‌شده به [عیار, گرم]. */
    private function silverGrams(string $item, float $qty): array
    {
        $purity = str_contains($item, '995') ? '995' : '999';
        $grams = str_starts_with($item, 'mithqal_')
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
