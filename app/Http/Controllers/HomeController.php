<?php

namespace App\Http\Controllers;

use App\Models\PriceSnapshot;
use App\Services\PriceService;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(private PriceService $prices) {}

    public function index()
    {
        return Inertia::render('Home', [
            'prices'         => $this->latestPrices(),
            'refreshSeconds' => (int) env('REFRESH_SECONDS', 30),
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
        return PriceSnapshot::latestPayload() ?? $this->prices->all();
    }
}
