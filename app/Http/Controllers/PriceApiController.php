<?php

namespace App\Http\Controllers;

use App\Models\PriceSnapshot;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class PriceApiController extends Controller
{
    public function __construct(private PriceService $prices) {}

    /** آخرین اسنپ‌شات قیمت‌ها؛ در نبود اسنپ‌شات، دریافت زنده انجام می‌شود. */
    public function index(): JsonResponse
    {
        $prices = Schema::hasTable('price_snapshots')
            ? PriceSnapshot::latestPayload()
            : null;

        return response()->json($prices ?? $this->prices->all());
    }
}
