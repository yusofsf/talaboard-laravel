<?php

namespace Tests\Feature;

use App\Models\TradeRoomOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeRoomListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sell_offers_are_sorted_cheapest_first_and_buy_offers_priciest_first(): void
    {
        $viewer = User::factory()->vip()->create();
        $seller = User::factory()->vip()->create();
        $buyer  = User::factory()->vip()->create();

        foreach ([300000, 100000, 200000] as $price) {
            TradeRoomOffer::create([
                'user_id' => $seller->id, 'metal' => 'gold', 'purity' => '', 'side' => 'sell',
                'grams' => 100, 'price_per_gram' => $price, 'status' => 'open',
            ]);
        }
        foreach ([100000, 300000, 200000] as $price) {
            TradeRoomOffer::create([
                'user_id' => $buyer->id, 'metal' => 'gold', 'purity' => '', 'side' => 'buy',
                'grams' => 100, 'price_per_gram' => $price, 'status' => 'open',
            ]);
        }

        $response = $this->actingAs($viewer)->get('/trade-room');

        $response->assertInertia(fn ($page) => $page
            ->where('sellOffers.0.price_per_gram', 100000)
            ->where('sellOffers.1.price_per_gram', 200000)
            ->where('sellOffers.2.price_per_gram', 300000)
            ->where('buyOffers.0.price_per_gram', 300000)
            ->where('buyOffers.1.price_per_gram', 200000)
            ->where('buyOffers.2.price_per_gram', 100000));
    }
}
