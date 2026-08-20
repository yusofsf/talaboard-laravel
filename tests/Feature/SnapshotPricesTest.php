<?php

namespace Tests\Feature;

use App\Models\GoldPrice;
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
                'ounce' => ['gold' => 3_345.67],
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
        ]);
        $this->assertCount(1, GoldPrice::all());
        $this->assertDatabaseCount('price_snapshots', 1);
    }
}
