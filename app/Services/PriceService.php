<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceService
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

    // key داخلی ما → (stockId در طلالند، حالت: direct | gram)
    private const STOCK_MAP = [
        'mithqal' => ['ABSHODE',   'direct'],  // مثقال طلا (آبشده)
        'geram' => ['ABSHODE',   'gram'],    // گرم طلا = مثقال ÷ وزن مثقال
        'bahar' => ['SEKKE',     'direct'],  // سکه تمام / بهار آزادی
        'nim' => ['SEKKE-NIM', 'direct'],  // نیم سکه
        'rob' => ['SEKKE-ROB', 'direct'],  // ربع سکه
    ];

    private int $cacheTtl;

    private float $mithqalGrams;

    private float $factor;

    public function __construct()
    {
        $this->cacheTtl = (int) env('CACHE_TTL', 30);
        $this->mithqalGrams = (float) env('MITHQAL_GRAMS', 4.3318);
        $this->factor = (float) env('GOLD_FACTOR', 0);
    }

    public function all(): array
    {
        $errors = [];

        $goldData = $this->fetchGold($errors);
        $silverData = $this->fetchSilver($errors);
        $dollar = $this->fetchDollar($errors);

        // طلا: فروش از askPrice، خرید از bidPrice طلالند — هرکدام با فاکتور خودش
        $gold = $this->applySpread($goldData['ask'], 1 + $this->factor);
        $goldBuy = $this->applySpread($goldData['bid'], 1 - $this->factor);

        // نقره: ستون‌های فروش/خرید مستقیماً از دیتابیس ربات نقره می‌آیند (بدون فاکتور)
        $silver = $silverData['sell'];
        $silverBuy = $silverData['buy'];

        $ounce = [
            'gold' => $this->fetchGoldOunce($errors),
            'silver' => $this->fetchSilverOunce($silverData['ounce'] ?? null),
        ];

        $open = $this->trackOpenPrices(compact('gold', 'silver', 'dollar'));

        return [
            'gold' => $gold,
            'gold_buy' => $goldBuy,
            'silver' => $silver,
            'silver_buy' => $silverBuy,
            'dollar' => $dollar,
            'ounce' => $ounce,
            'open' => $open,
            'errors' => $errors,
            'updated_at' => now()->setTimezone('Asia/Tehran')->format('H:i:s'),
        ];
    }

    /** Remove source/transport diagnostics before a price payload is sent to clients. */
    public static function publicPayload(array $payload): array
    {
        $payload['errors'] = array_values(array_filter(
            $payload['errors'] ?? [],
            static fn ($error) => is_string($error)
                && ! str_contains($error, "\u{062F}\u{0644}\u{0627}\u{0631}")
                && ! preg_match('/(?:انس\\s+(?:طلا|نقره)|curl)/iu', $error)
        ));

        return $payload;
    }

    /** ضرب همه‌ی مقادیر عددی یک آرایه‌ی قیمت در ضریب (برای ساخت قیمت خرید/فروش از روی قیمت میانی). */
    private function applySpread(array $mid, float $mult): array
    {
        $out = [];
        foreach ($mid as $key => $v) {
            $out[$key] = is_numeric($v) ? (is_int($v) ? (int) round($v * $mult) : round($v * $mult, 2)) : $v;
        }

        return $out;
    }

    /**
     * مبنای فروش (askPrice) و خرید (bidPrice) طلا و سکه از REST API طلالند — کش‌شده.
     * فروش = askPrice × (۱+فاکتور)   |   خرید = bidPrice × (۱−فاکتور)
     */
    private function fetchGold(array &$errors): array
    {
        $nullSide = array_fill_keys(array_keys(self::STOCK_MAP), null);
        $null = ['ask' => $nullSide, 'bid' => $nullSide];

        return Cache::remember('prices.gold', $this->cacheTtl, function () use (&$errors, $null) {
            try {
                $base = rtrim(env('TALALAND_API_BASE', 'https://api.talaland.net/api'), '/');
                $username = env('TALALAND_USERNAME', '');
                $token = env('TALALAND_TOKEN', '');

                if (! $username || ! $token) {
                    $errors[] = 'طلا: نام‌کاربری/توکن طلالند تنظیم نشده';

                    return $null;
                }

                $res = Http::timeout(15)
                    ->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])
                    ->get("{$base}/getAllPrices/{$username}/{$token}");

                $js = $res->json();

                if (! $js || ($js['hasError'] ?? false) || ! in_array($js['resultCode'] ?? 0, [0, null], true)) {
                    $errors[] = 'طلا: '.($js['message'] ?? 'خطای نامشخص');

                    return $null;
                }

                $byId = [];
                foreach (($js['result'] ?? []) as $it) {
                    if (isset($it['stockId'])) {
                        $byId[$it['stockId']] = $it;
                    }
                }

                $ask = [];
                $bid = [];
                foreach (self::STOCK_MAP as $key => [$sid, $mode]) {
                    $a = $byId[$sid]['askPrice'] ?? null;
                    $b = $byId[$sid]['bidPrice'] ?? null;
                    $ask[$key] = $a !== null ? (int) round($mode === 'gram' ? $a / $this->mithqalGrams : $a) : null;
                    $bid[$key] = $b !== null ? (int) round($mode === 'gram' ? $b / $this->mithqalGrams : $b) : null;
                }

                return ['ask' => $ask, 'bid' => $bid];
            } catch (\Throwable $e) {
                Log::warning('PriceService gold fetch failed', ['exception' => $e]);

                return $null;
            }
        });
    }

    /**
     * قیمت فروش و خرید نقره از دیتابیس ربات نقره (sachmebot_laravel) — آخرین رکورد.
     * ستون‌های _buy همان قیمت خرید واقعی ربات هستند؛ اینجا فاکتور اعمال نمی‌شود.
     */
    private function fetchSilver(array &$errors): array
    {
        $nullSide = ['mithqal_999' => null, 'gram_999' => null, 'mithqal_995' => null, 'gram_995' => null];
        $null = ['sell' => $nullSide, 'buy' => $nullSide, 'ounce' => null];

        return Cache::remember('prices.silver', $this->cacheTtl, function () use (&$errors, $null) {
            try {
                $row = DB::connection('silver')->table('silver_prices')->orderByDesc('id')->first();
                if (! $row) {
                    $errors[] = 'نقره: رکوردی در دیتابیس یافت نشد';

                    return $null;
                }
                $ounce = isset($row->silver_ounce) ? (float) $row->silver_ounce : null;
                if ($ounce === null) {
                    Log::warning('PriceService silver ounce unavailable');
                }

                return [
                    'sell' => [
                        'mithqal_999' => (int) round($row->mithqal_price),
                        'gram_999' => (float) $row->gram_price,
                        'mithqal_995' => isset($row->mithqal_995_price) ? (int) round($row->mithqal_995_price) : null,
                        'gram_995' => isset($row->gram_995) ? (float) $row->gram_995 : null,
                    ],
                    'buy' => [
                        'mithqal_999' => isset($row->mithqal_price_buy) ? (int) round($row->mithqal_price_buy) : null,
                        'gram_999' => isset($row->gram_price_buy) ? (float) $row->gram_price_buy : null,
                        'mithqal_995' => isset($row->mithqal_995_price_buy) ? (int) round($row->mithqal_995_price_buy) : null,
                        'gram_995' => isset($row->gram_995_buy) ? (float) $row->gram_995_buy : null,
                    ],
                    'ounce' => $ounce,
                ];
            } catch (\Throwable $e) {
                Log::warning('PriceService silver fetch failed', ['exception' => $e]);

                return $null;
            }
        });
    }

    /** قیمت فروش دلار از alanchand.com با TGJU به‌عنوان fallback. */
    private function fetchDollar(array &$errors): array
    {
        return Cache::remember('prices.dollar', $this->cacheTtl, function () use (&$errors) {
            $price = null;

            try {
                $url = env('DOLLAR_HOME_URL', 'https://alanchand.com/');
                $res = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);
                $price = $res->ok() ? $this->findSellInTables($res->body(), 'دلار آمریکا') : null;
            } catch (\Throwable $e) {
                Log::warning('PriceService dollar (alanchand) fetch failed: '.$e->getMessage());
            }

            if ($price === null) {
                $tgjuPrice = $this->fetchTgjuProfilePrice(
                    env('TGJU_DOLLAR_URL', 'https://www.tgju.org/profile/price_dollar_rl'),
                    'dollar'
                );

                // TGJU publishes the free-dollar profile in rial; this app exposes toman.
                $price = $tgjuPrice !== null ? $tgjuPrice / 10 : null;
            }

            if ($price === null) {
                $errors[] = 'دلار: قیمت دریافت نشد';
            }

            return ['price' => $price !== null ? (int) round($price) : null, 'label' => 'دلار آمریکا'];
        });
    }

    /**
     * انس طلا (دلار) — اول alanchand.com/gold-price، سپس TGJU و در نهایت Yahoo Finance.
     */
    private function fetchGoldOunce(array &$errors): ?float
    {
        return Cache::remember('prices.ounce_gold', $this->cacheTtl, function () use (&$errors) {
            try {
                $url = env('DOLLAR_GOLD_URL', 'https://alanchand.com/gold-price');
                $res = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);
                $v = $res->ok() ? $this->findSellInTables($res->body(), 'انس طلا', 1) : null;
                if ($v !== null) {
                    return (float) $v;
                }

                Log::warning(
                    $res->ok()
                        ? 'PriceService gold ounce (alanchand) parse failed'
                        : "PriceService gold ounce (alanchand) returned HTTP {$res->status()}"
                );
            } catch (\Throwable $e) {
                Log::warning('PriceService gold ounce (alanchand) fetch failed: '.$e->getMessage());
            }

            $v = $this->fetchTgjuProfilePrice(
                env('TGJU_GOLD_OUNCE_URL', 'https://www.tgju.org/profile/ons'),
                'gold ounce'
            );
            if ($v !== null) {
                return $v;
            }

            $v = $this->fetchGoldOunceYahoo();
            if ($v !== null) {
                return $v;
            }

            Log::warning('PriceService gold ounce unavailable after all sources failed');

            return null;
        });
    }

    /**
     * انس نقره — ابتدا قیمت لحظه‌ای alanchand، سپس TGJU و Yahoo؛ مقدار دیتابیس fallback نهایی است.
     */
    private function fetchSilverOunce(?float $fallback = null): ?float
    {
        return Cache::remember('prices.ounce_silver', $this->cacheTtl, function () use ($fallback) {
            try {
                $url = env('DOLLAR_GOLD_URL', 'https://alanchand.com/gold-price');
                $res = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);
                $price = $res->ok() ? $this->findSellInTables($res->body(), 'انس نقره', 1) : null;
                if ($price !== null) {
                    return (float) $price;
                }

                Log::warning(
                    $res->ok()
                        ? 'PriceService silver ounce (alanchand) parse failed'
                        : "PriceService silver ounce (alanchand) returned HTTP {$res->status()}"
                );
            } catch (\Throwable $e) {
                Log::warning('PriceService silver ounce (alanchand) fetch failed: '.$e->getMessage());
            }

            $price = $this->fetchTgjuTodayLatestPrice(
                env('TGJU_SILVER_URL', 'https://www.tgju.org/profile/silver/today'),
                'silver ounce'
            );

            if ($price !== null) {
                return $price;
            }

            $price = $this->fetchSilverOunceYahoo();
            if ($price !== null) {
                return $price;
            }

            Log::warning('PriceService silver ounce unavailable after alanchand, TGJU, and Yahoo; using database fallback');

            return $fallback;
        });
    }

    private function fetchTgjuProfilePrice(string $url, string $market): ?float
    {
        try {
            $res = Http::timeout(10)->withHeaders(['User-Agent' => self::UA])->get($url);
            if (! $res->ok()) {
                return null;
            }

            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$res->body());
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            $nodes = (new \DOMXPath($dom))->query('//*[@data-col="info.last_trade.PDrCotVal"]');
            if ($nodes === false || $nodes->length === 0) {
                return null;
            }

            return $this->parseNumber($nodes->item(0)->textContent);
        } catch (\Throwable $e) {
            Log::warning("PriceService {$market} (TGJU) fetch failed: ".$e->getMessage());

            return null;
        }
    }

    /** آخرین ردیف زمانی جدول روز جاری TGJU را استخراج می‌کند. */
    private function fetchTgjuTodayLatestPrice(string $url, string $market): ?float
    {
        try {
            $res = Http::timeout(10)->withHeaders([
                'User-Agent' => self::UA,
                'Accept' => 'text/html,application/xhtml+xml',
            ])->get($url);
            if (! $res->ok()) {
                return null;
            }

            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$res->body());
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            $latestPrice = null;
            $latestSeconds = -1;

            foreach ($dom->getElementsByTagName('table') as $table) {
                $tableText = preg_replace('/\s+/u', ' ', $table->textContent);
                if (! str_contains($tableText, 'قیمت') || ! str_contains($tableText, 'زمان')) {
                    continue;
                }

                foreach ($table->getElementsByTagName('tr') as $tr) {
                    $cells = [];
                    foreach ($tr->getElementsByTagName('td') as $td) {
                        $cells[] = trim($td->textContent);
                    }
                    if (count($cells) < 2) {
                        continue;
                    }

                    $time = $this->latinDigits($cells[1]);
                    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $parts) !== 1) {
                        continue;
                    }

                    $price = $this->parseNumber($cells[0]);
                    if ($price === null) {
                        continue;
                    }

                    $seconds = ((int) $parts[1] * 3600) + ((int) $parts[2] * 60) + (int) ($parts[3] ?? 0);
                    if ($seconds > $latestSeconds) {
                        $latestSeconds = $seconds;
                        $latestPrice = $price;
                    }
                }
            }

            return $latestPrice;
        } catch (\Throwable $e) {
            Log::warning("PriceService {$market} (TGJU today) fetch failed: ".$e->getMessage());

            return null;
        }
    }

    private function fetchGoldOunceYahoo(): ?float
    {
        return $this->fetchOunceYahoo(env('GOLD_OUNCE_SYMBOL', 'GC=F'), 'gold');
    }

    private function fetchSilverOunceYahoo(): ?float
    {
        return $this->fetchOunceYahoo(env('SILVER_OUNCE_SYMBOL', 'SI=F'), 'silver');
    }

    private function fetchOunceYahoo(string $symbol, string $market): ?float
    {
        try {
            $res = Http::timeout(5)
                ->withHeaders(['User-Agent' => self::UA])
                ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}");

            $price = $res->json('chart.result.0.meta.regularMarketPrice');

            return $price !== null ? (float) $price : null;
        } catch (\Throwable $e) {
            Log::warning("PriceService {$market} ounce (Yahoo) fetch failed: ".$e->getMessage());

            return null;
        }
    }

    /** در جدول‌های HTML، ستون۰ نام بازار است؛ شماره ستون قیمت بین صفحات متفاوت است. */
    private function findSellInTables(string $html, string $keyword, int $priceColumn = 2): ?float
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors($prev);

        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells[] = trim($td->textContent);
            }
            if (isset($cells[0], $cells[$priceColumn]) && mb_strpos($cells[0], $keyword) !== false) {
                return $this->parseNumber($cells[$priceColumn]);
            }
        }

        return null;
    }

    private function parseNumber(string $s): ?float
    {
        $s = $this->latinDigits($s);
        $s = str_replace([',', '٬'], '', $s);

        return preg_match('/\d+(?:\.\d+)?/', $s, $matches) === 1
            ? (float) $matches[0]
            : null;
    }

    private function latinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * قیمت ابتدای روز (شمسی، به‌وقت تهران) برای هر شاخص — مبنای محاسبه‌ی درصد تغییرات.
     * اولین قیمت غیرخالی هر روز ثبت و تا پایان همان روز نگه داشته می‌شود.
     */
    private function trackOpenPrices(array $sections): array
    {
        $todayKey = 'prices.open.'.now()->setTimezone('Asia/Tehran')->format('Y-m-d');
        $open = Cache::get($todayKey, []);
        $changed = false;

        foreach ($sections as $section => $vals) {
            if (! is_array($vals)) {
                continue;
            }
            foreach ($vals as $k => $v) {
                $flatKey = "{$section}.{$k}";
                if ($v !== null && ! array_key_exists($flatKey, $open)) {
                    $open[$flatKey] = $v;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            Cache::put($todayKey, $open, now()->setTimezone('Asia/Tehran')->endOfDay()->addMinutes(10));
        }

        return $open;
    }
}
