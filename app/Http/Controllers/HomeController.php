<?php

namespace App\Http\Controllers;

use App\Models\PriceSnapshot;
use App\Services\PriceService;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(private PriceService $prices) {}

    public function index()
    {
        return Inertia::render('Home', [
            'prices'         => $this->latestPrices(),
            'refreshSeconds' => (int) env('REFRESH_SECONDS', 30),
            'seo'            => $this->seo(),
        ]);
    }

    public function prices()
    {
        return response()->json($this->latestPrices());
    }

    /**
     * آخرین عکس فوری قیمت‌ها از دیتابیس (که فرمان prices:snapshot هر ۱۰ ثانیه می‌نویسد).
     * اگر هنوز هیچ رکوردی نوشته نشده (مثلاً scheduler اجرا نشده)، یک‌بار زنده می‌گیرد.
     */
    private function latestPrices(): array
    {
        if (! Schema::hasTable('price_snapshots')) {
            return [
                'gold' => [],
                'silver' => [],
                'dollar' => [],
                'ounce' => [],
                'open' => [],
                'errors' => [],
                'updated_at' => now()->setTimezone('Asia/Tehran')->format('H:i:s'),
            ];
        }

        return PriceSnapshot::latestPayload() ?? $this->prices->all();
    }

    private function seo(): array
    {
        $siteUrl = rtrim(config('seo.url'), '/');
        $page = config('seo.public_pages.home');

        return [
            ...$page,
            'canonical' => $siteUrl . '/',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $page['title'],
                'description' => $page['description'],
                'url' => $siteUrl . '/',
                'inLanguage' => 'fa-IR',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => config('seo.site_name'),
                    'url' => $siteUrl . '/',
                ],
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'name' => 'تابلوی قیمت طلا، نقره و سکه',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'قیمت نقره', 'url' => $siteUrl . '/silver-prices'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'قیمت طلا', 'url' => $siteUrl . '/gold-prices'],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => 'قیمت سکه', 'url' => $siteUrl . '/coin-prices'],
                    ],
                ],
            ],
        ];
    }
}
