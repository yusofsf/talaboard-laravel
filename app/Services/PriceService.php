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
        'geram'   => ['ABSHODE',   'gram'],    // گرم طلا = مثقال ÷ وزن مثقال
        'bahar'   => ['SEKKE',     'direct'],  // سکه تمام / بهار آزادی
        'nim'     => ['SEKKE-NIM', 'direct'],  // نیم سکه
        'rob'     => ['SEKKE-ROB', 'direct'],  // ربع سکه
    ];

    private int   $cacheTtl;
    private float $mithqalGrams;
    private float $factor;

    public function __construct()
    {
        $this->cacheTtl     = (int) env('CACHE_TTL', 30);
        $this->mithqalGrams = (float) env('MITHQAL_GRAMS', 4.3318);
        $this->factor       = (float) env('GOLD_FACTOR', 0);
    }

    public function all(): array
    {
        $errors = [];

        $gold   = $this->fetchGold($errors);
        $silver = $this->fetchSilver($errors);
        $dollar = $this->fetchDollar($errors);
        $ounce  = [
            'gold'   => $this->fetchGoldOunce($errors),
            'silver' => $silver['ounce'] ?? null,
        ];
        unset($silver['ounce']);

        $open = $this->trackOpenPrices(compact('gold', 'silver', 'dollar'));

        return [
            'gold'       => $gold,
            'silver'     => $silver,
            'dollar'     => $dollar,
            'ounce'      => $ounce,
            'open'       => $open,
            'errors'     => $errors,
            'updated_at' => now()->setTimezone('Asia/Tehran')->format('H:i:s'),
        ];
    }

    /** قیمت فروش طلا و سکه از REST API طلالند — کش‌شده. */
    private function fetchGold(array &$errors): array
    {
        $null = array_fill_keys(array_keys(self::STOCK_MAP), null);

        return Cache::remember('prices.gold', $this->cacheTtl, function () use (&$errors, $null) {
            try {
                $base     = rtrim(env('TALALAND_API_BASE', 'https://api.talaland.net/api'), '/');
                $username = env('TALALAND_USERNAME', '');
                $token    = env('TALALAND_TOKEN', '');

                if (!$username || !$token) {
                    $errors[] = 'طلا: نام‌کاربری/توکن طلالند تنظیم نشده';
                    return $null;
                }

                $res = Http::timeout(15)
                    ->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])
                    ->get("{$base}/getAllPrices/{$username}/{$token}");

                $js = $res->json();

                if (!$js || ($js['hasError'] ?? false) || !in_array($js['resultCode'] ?? 0, [0, null], true)) {
                    $errors[] = 'طلا: ' . ($js['message'] ?? 'خطای نامشخص');
                    return $null;
                }

                $byId = [];
                foreach (($js['result'] ?? []) as $it) {
                    if (isset($it['stockId'])) $byId[$it['stockId']] = $it;
                }

                $out  = [];
                $mult = 1 + $this->factor;
                foreach (self::STOCK_MAP as $key => [$sid, $mode]) {
                    $ask = $byId[$sid]['askPrice'] ?? null;
                    if ($ask === null) {
                        $out[$key] = null;
                        continue;
                    }
                    $base = $mode === 'gram' ? $ask / $this->mithqalGrams : $ask;
                    $out[$key] = (int) round($base * $mult);
                }
                return $out;
            } catch (\Throwable $e) {
                Log::warning('PriceService gold fetch failed: ' . $e->getMessage());
                $errors[] = 'طلا: ' . $e->getMessage();
                return $null;
            }
        });
    }

    /** قیمت نقره از دیتابیس ربات نقره (sachmebot_laravel) — آخرین رکورد. */
    private function fetchSilver(array &$errors): array
    {
        $null = [
            'mithqal_999' => null, 'gram_999' => null,
            'mithqal_995' => null, 'gram_995' => null, 'ounce' => null,
        ];

        return Cache::remember('prices.silver', $this->cacheTtl, function () use (&$errors, $null) {
            try {
                $row = DB::connection('silver')->table('silver_prices')->orderByDesc('id')->first();
                if (!$row) {
                    $errors[] = 'نقره: رکوردی در دیتابیس یافت نشد';
                    return $null;
                }
                return [
                    'mithqal_999' => (int) round($row->mithqal_price),
                    'gram_999'    => (float) $row->gram_price,
                    'mithqal_995' => isset($row->mithqal_995_price) ? (int) round($row->mithqal_995_price) : null,
                    'gram_995'    => isset($row->gram_995) ? (float) $row->gram_995 : null,
                    'ounce'       => isset($row->silver_ounce) ? (float) $row->silver_ounce : null,
                ];
            } catch (\Throwable $e) {
                Log::warning('PriceService silver fetch failed: ' . $e->getMessage());
                $errors[] = 'نقره: ' . $e->getMessage();
                return $null;
            }
        });
    }

    /** قیمت فروش دلار از alanchand.com (جدول HTML). */
    private function fetchDollar(array &$errors): array
    {
        return Cache::remember('prices.dollar', $this->cacheTtl, function () use (&$errors) {
            try {
                $url = env('DOLLAR_HOME_URL', 'https://alanchand.com/');
                $res = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);
                $price = $res->ok() ? $this->findSellInTables($res->body(), 'دلار آمریکا') : null;
                if ($price === null) $errors[] = 'دلار: قیمت استخراج نشد';
                return ['price' => $price !== null ? (int) round($price) : null, 'label' => 'دلار آمریکا'];
            } catch (\Throwable $e) {
                Log::warning('PriceService dollar fetch failed: ' . $e->getMessage());
                $errors[] = 'دلار: ' . $e->getMessage();
                return ['price' => null, 'label' => 'دلار آمریکا'];
            }
        });
    }

    /** انس طلا (دلار) از alanchand.com/gold-price. */
    private function fetchGoldOunce(array &$errors): ?float
    {
        return Cache::remember('prices.ounce_gold', $this->cacheTtl, function () use (&$errors) {
            try {
                $url = env('DOLLAR_GOLD_URL', 'https://alanchand.com/gold-price');
                $res = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);
                $v = $res->ok() ? $this->findSellInTables($res->body(), 'انس طلا') : null;
                return $v !== null ? (float) $v : null;
            } catch (\Throwable $e) {
                Log::warning('PriceService gold ounce fetch failed: ' . $e->getMessage());
                $errors[] = 'انس طلا: ' . $e->getMessage();
                return null;
            }
        });
    }

    /** در جدول‌های HTML: ستون۰=نام، ستون۲=قیمت فروش. */
    private function findSellInTables(string $html, string $keyword): ?float
    {
        $prev = libxml_use_internal_errors(true);
        $dom  = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_use_internal_errors($prev);

        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells[] = trim($td->textContent);
            }
            if (count($cells) >= 3 && mb_strpos($cells[0], $keyword) !== false) {
                return $this->parseNumber($cells[2]);
            }
        }
        return null;
    }

    private function parseNumber(string $s): ?float
    {
        $s = strtr($s, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
        $s = preg_replace('/[^0-9.]/', '', $s);
        return $s === '' ? null : (float) $s;
    }

    /**
     * قیمت ابتدای روز (شمسی، به‌وقت تهران) برای هر شاخص — مبنای محاسبه‌ی درصد تغییرات.
     * اولین قیمت غیرخالی هر روز ثبت و تا پایان همان روز نگه داشته می‌شود.
     */
    private function trackOpenPrices(array $sections): array
    {
        $todayKey = 'prices.open.' . now()->setTimezone('Asia/Tehran')->format('Y-m-d');
        $open     = Cache::get($todayKey, []);
        $changed  = false;

        foreach ($sections as $section => $vals) {
            if (!is_array($vals)) continue;
            foreach ($vals as $k => $v) {
                $flatKey = "{$section}.{$k}";
                if ($v !== null && !array_key_exists($flatKey, $open)) {
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
