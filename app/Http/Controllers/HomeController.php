<?php

namespace App\Http\Controllers;

use App\Services\PriceService;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(private PriceService $prices) {}

    public function index()
    {
        return Inertia::render('Home', [
            'prices'         => $this->prices->all(),
            'refreshSeconds' => (int) env('REFRESH_SECONDS', 30),
        ]);
    }

    public function prices()
    {
        return response()->json($this->prices->all());
    }
}
