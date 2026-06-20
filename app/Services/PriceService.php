<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
        return Cache::remember('prices', $this->cacheTtl, fn () => [
            'gold'       => $this->fetchGold(),
            'dollar'     => $this->fetchDollar(),
            'updated_at' => now()->setTimezone('Asia/Tehran')->format('H:i:s'),
        ]);
    }

    /** قیمت فروش طلا و سکه از REST API طلالند. */
    private function fetchGold(): array
    {
        try {
            $base     = rtrim(env('TALALAND_API_BASE', 'https://api.talaland.net/api'), '/');
            $username = env('TALALAND_USERNAME', '');
            $token    = env('TALALAND_TOKEN', '');

            if (!$username || !$token) {
                Log::warning('PriceService: TALALAND_USERNAME/TOKEN خالی است.');
                return [];
            }

            $res = Http::timeout(15)
                ->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])
                ->get("{$base}/getAllPrices/{$username}/{$token}");

            $js = $res->json();

            if (!$js || ($js['hasError'] ?? false) || !in_array($js['resultCode'] ?? 0, [0, null], true)) {
                Log::warning('PriceService طلالند خطا: ' . ($js['message'] ?? 'نامشخص'));
                return [];
            }

            // ایندکس بر اساس stockId
            $byId = [];
            foreach (($js['result'] ?? []) as $it) {
                if (isset($it['stockId'])) {
                    $byId[$it['stockId']] = $it;
                }
            }

            $out  = [];
            $mult = 1 + $this->factor;   // فروش = askPrice × (۱ + ضریب)
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
            return [];
        }
    }

    /** قیمت فروش دلار از alanchand.com (جدول HTML). */
    private function fetchDollar(): ?int
    {
        try {
            $url = env('DOLLAR_HOME_URL', 'https://alanchand.com/');
            $res = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);
            if (!$res->ok()) return null;

            $price = $this->findSellInTables($res->body(), 'دلار آمریکا');
            return $price !== null ? (int) round($price) : null;
        } catch (\Throwable $e) {
            Log::warning('PriceService dollar fetch failed: ' . $e->getMessage());
            return null;
        }
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
        // ارقام فارسی/عربی → لاتین، حذف جداکننده‌ها
        $s = strtr($s, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
        $s = preg_replace('/[^0-9.]/', '', $s);
        return $s === '' ? null : (float) $s;
    }
}
