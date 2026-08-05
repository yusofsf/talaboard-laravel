<?php

namespace Tests\Unit;

use App\Services\PriceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_dollar_falls_back_to_tgju_and_converts_rial_to_toman(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://alanchand.com/') {
                return Http::response('', 504);
            }

            return Http::response('<span data-col="info.last_trade.PDrCotVal">۱٬۵۲۴٬۸۰۰</span>');
        });

        $errors = [];
        $result = $this->invokePriceFetcher('fetchDollar', $errors);

        $this->assertSame(152480, $result['price']);
        $this->assertSame([], $errors);
    }

    public function test_gold_ounce_uses_tgju_before_yahoo(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://alanchand.com/gold-price') {
                return Http::response('', 504);
            }

            return Http::response(
                '<span class="value" data-col="info.last_trade.PDrCotVal">4,797.33</span>'
            );
        });

        $errors = [];
        $result = $this->invokePriceFetcher('fetchGoldOunce', $errors);

        $this->assertSame(4797.33, $result);
        $this->assertSame([], $errors);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'finance.yahoo.com'));
    }

    private function invokePriceFetcher(string $methodName, array &$errors): mixed
    {
        $method = new ReflectionMethod(PriceService::class, $methodName);

        return $method->invokeArgs(new PriceService, [&$errors]);
    }
}
