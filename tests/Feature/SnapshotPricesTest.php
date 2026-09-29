<?php

namespace Tests\Feature;

use App\Models\GoldPrice;
use App\Models\PriceSnapshot;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SnapshotPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_command_stores_all_gold_prices_in_normalized_history(): void
    {
        $this->mock(PriceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('all')->once()->andReturn([
                'gold' => [
                    'bahar' => 900_000_000,
                    'nim' => 500_000_000,
                    'rob' => 300_000_000,
                    'mithqal' => 350_000_000,
                    'geram' => 80_800_000,
                ],
                'gold_buy' => [
                    'bahar' => 890_000_000,
                    'nim' => 490_000_000,
                    'rob' => 290_000_000,
                    'mithqal' => 345_000_000,
                    'geram' => 79_600_000,
                ],
                'ounce' => ['gold' => 3_345.67, 'silver' => 38.42],
            ]);
        });

        $this->artisan('prices:snapshot')->assertSuccessful();

        $this->assertDatabaseHas('gold_prices', [
            'bahar_sell' => 900_000_000,
            'bahar_buy' => 890_000_000,
            'nim_sell' => 500_000_000,
            'nim_buy' => 490_000_000,
            'rob_sell' => 300_000_000,
            'rob_buy' => 290_000_000,
            'mithqal_sell' => 350_000_000,
            'mithqal_buy' => 345_000_000,
            'geram_sell' => 80_800_000,
            'geram_buy' => 79_600_000,
            'ounce' => 3_345.67,
            'silver_ounce' => 38.42,
        ]);
        $this->assertCount(1, GoldPrice::all());
        $this->assertDatabaseCount('price_snapshots', 1);
    }

    public function test_snapshot_falls_back_to_last_known_gold_when_source_returns_nulls(): void
    {
        $this->mock(PriceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('all')->twice()->andReturn(
                $this->fullGoldPayload(),
                $this->nullGoldPayload(),
            );
        });

        $this->artisan('prices:snapshot')->assertSuccessful();
        $this->artisan('prices:snapshot')->assertSuccessful();

        $payload = PriceSnapshot::latestPayload();

        $this->assertSame(900_000_000, $payload['gold']['bahar']);
        $this->assertSame(350_000_000, $payload['gold']['mithqal']);
        $this->assertSame(80_800_000, $payload['gold']['geram']);
        $this->assertSame(79_600_000, $payload['gold_buy']['geram']);
        $this->assertEqualsWithDelta(3_345.67, $payload['ounce']['gold'], 0.0001);

        // تاریخچه هم مقادیر سرویشده را نگه میدارد تا خوانندههای مستقیم جدول (مثل ربات) null نبینند.
        $latestHistory = GoldPrice::query()->latest('id')->first();
        $this->assertSame(900_000_000, $latestHistory->bahar_sell);
        $this->assertSame(79_600_000, $latestHistory->geram_buy);
        $this->assertEqualsWithDelta(3_345.67, $latestHistory->ounce, 0.0001);
        $this->assertEqualsWithDelta(38.42, $latestHistory->silver_ounce, 0.0001);
        $this->assertCount(2, GoldPrice::all());
    }

    public function test_snapshot_fills_only_missing_gold_keys_from_history(): void
    {
        $this->mock(PriceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('all')->twice()->andReturn(
                $this->fullGoldPayload(),
                [
                    'gold' => [
                        'bahar' => null,
                        'nim' => null,
                        'rob' => null,
                        'mithqal' => 360_000_000,
                        'geram' => null,
                    ],
                    'gold_buy' => [
                        'bahar' => null,
                        'nim' => null,
                        'rob' => null,
                        'mithqal' => 355_000_000,
                        'geram' => null,
                    ],
                    'ounce' => ['gold' => null, 'silver' => null],
                ],
            );
        });

        $this->artisan('prices:snapshot')->assertSuccessful();
        $this->artisan('prices:snapshot')->assertSuccessful();

        $payload = PriceSnapshot::latestPayload();

        // کلیدهای زنده دستنخورده میمانند و فقط کلیدهای null از تاریخچه پر میشوند.
        $this->assertSame(360_000_000, $payload['gold']['mithqal']);
        $this->assertSame(355_000_000, $payload['gold_buy']['mithqal']);
        $this->assertSame(900_000_000, $payload['gold']['bahar']);
        $this->assertSame(79_600_000, $payload['gold_buy']['geram']);
    }

    public function test_snapshot_leaves_gold_null_when_no_history_exists(): void
    {
        $this->mock(PriceService::class, function (MockInterface $mock) {
            $mock->shouldReceive('all')->once()->andReturn($this->nullGoldPayload());
        });

        $this->artisan('prices:snapshot')->assertSuccessful();

        $payload = PriceSnapshot::latestPayload();

        $this->assertNull($payload['gold']['bahar']);
        $this->assertNull($payload['gold_buy']['geram']);
    }

    private function fullGoldPayload(): array
    {
        return [
            'gold' => [
                'bahar' => 900_000_000,
                'nim' => 500_000_000,
                'rob' => 300_000_000,
                'mithqal' => 350_000_000,
                'geram' => 80_800_000,
            ],
            'gold_buy' => [
                'bahar' => 890_000_000,
                'nim' => 490_000_000,
                'rob' => 290_000_000,
                'mithqal' => 345_000_000,
                'geram' => 79_600_000,
            ],
            'ounce' => ['gold' => 3_345.67, 'silver' => 38.42],
        ];
    }

    private function nullGoldPayload(): array
    {
        $nulls = array_fill_keys(['bahar', 'nim', 'rob', 'mithqal', 'geram'], null);

        return [
            'gold' => $nulls,
            'gold_buy' => $nulls,
            'ounce' => ['gold' => null, 'silver' => null],
        ];
    }
}
