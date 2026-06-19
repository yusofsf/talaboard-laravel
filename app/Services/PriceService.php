<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceService
{
    private int   $cacheTtl;
    private float $mithqalGrams;
    private float $goldFactor;

    public function __construct()
    {
        $this->cacheTtl     = (int) env('CACHE_TTL', 30);
        $this->mithqalGrams = (float) env('MITHQAL_GRAMS', 4.3318);
        $this->goldFactor   = (float) env('GOLD_FACTOR', 1.0);
    }

    public function all(): array
    {
        return Cache::remember('prices', $this->cacheTtl, fn() => $this->fetchAll());
    }

    private function fetchAll(): array
    {
        return [
            'gold'       => $this->fetchGold(),
            'dollar'     => $this->fetchDollar(),
            'updated_at' => now()->format('H:i:s'),
        ];
    }

    private function fetchGold(): array
    {
        try {
            $base     = rtrim(env('TALALAND_API_BASE', 'https://api.talaland.net/api'), '/');
            $username = env('TALALAND_USERNAME', '');
            $token    = env('TALALAND_TOKEN', '');

            $response = Http::timeout(8)->get("{$base}/prices", [
                'username' => $username,
                'token'    => $token,
            ]);

            if (!$response->ok()) return [];

            $data   = $response->json();
            $factor = $this->goldFactor;
            $mg     = $this->mithqalGrams;

            $result = [];
            foreach ($data as $item) {
                $key   = $item['symbol'] ?? null;
                $price = isset($item['price']) ? (int) round($item['price'] * $factor) : null;
                if ($key && $price) {
                    $result[$key] = $price;
                }
            }
            return $result;
        } catch (\Exception $e) {
            Log::warning('PriceService gold fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchDollar(): ?int
    {
        try {
            $url      = env('DOLLAR_HOME_URL', 'https://alanchand.com/');
            $response = Http::timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
            if (!$response->ok()) return null;

            preg_match('/دلار.*?(\d[\d,]+)/u', $response->body(), $m);
            if (isset($m[1])) {
                return (int) str_replace(',', '', $m[1]);
            }
            return null;
        } catch (\Exception $e) {
            Log::warning('PriceService dollar fetch failed: ' . $e->getMessage());
            return null;
        }
    }
}
