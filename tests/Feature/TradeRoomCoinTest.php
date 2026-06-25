<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TradeRoomOffer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeRoomCoinTest extends TestCase
{
    use RefreshDatabase;

    private function fund(User $u, int $amount): void
    {
        WalletTransaction::create(['user_id' => $u->id, 'amount' => $amount, 'type' => 'deposit', 'description' => 'x']);
    }

    /** به کاربر سکه می‌دهد (از طریق یک تراکنش خرید فعال، مثل فروشگاه). */
    private function giveCoins(User $u, string $item, int $qty): void
    {
        Transaction::create([
            'user_id' => $u->id, 'type' => 'buy', 'item' => $item, 'item_label' => 'سکه',
            'quantity' => $qty, 'price_per_unit' => 1, 'total' => $qty, 'status' => 'active',
        ]);
    }

    public function test_vip_can_post_and_complete_a_coin_sell_offer_with_commission(): void
    {
        Setting::put('trade_room_commission_percent', 0.1);
        $seller = User::factory()->vip()->create();
        $buyer  = User::factory()->vip()->create();
        $this->giveCoins($seller, 'bahar', 3);
        $this->fund($buyer, 1_000_000_000);

        // فروشنده ۲ سکه تمام به قیمت هر عدد ۵۰٬۰۰۰٬۰۰۰ می‌فروشد
        $this->actingAs($seller)->post('/trade-room', [
            'metal' => 'coin', 'item' => 'bahar', 'side' => 'sell', 'grams' => 2, 'price_per_gram' => 50_000_000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $offer = TradeRoomOffer::first();
        $this->assertSame('coin', $offer->metal);
        $this->assertSame('bahar', $offer->item);
        $total = 2 * 50_000_000;

        $this->actingAs($buyer)->post("/trade-room/{$offer->id}/accept")->assertRedirect();

        $offer->refresh();
        $fee = (int) round($total * 0.001);
        $buyerFee = intdiv($fee, 2);
        $sellerFee = $fee - $buyerFee;

        $this->assertSame('completed', $offer->status);
        $this->assertSame($fee, (int) $offer->commission);

        // مالکیت سکه منتقل شده: فروشنده ۱ مانده، خریدار ۲
        $this->assertSame(1.0, $this->coinHolding($seller, 'bahar'));
        $this->assertSame(2.0, $this->coinHolding($buyer, 'bahar'));

        // کیف پول‌ها با کارمزد نصف‌نصف
        $this->assertSame(1_000_000_000 - ($total + $buyerFee), $buyer->refresh()->walletBalance());
        $this->assertSame($total - $sellerFee, $seller->refresh()->walletBalance());
    }

    public function test_cannot_sell_more_coins_than_held(): void
    {
        $seller = User::factory()->vip()->create();
        $this->giveCoins($seller, 'nim', 1);

        $this->actingAs($seller)->post('/trade-room', [
            'metal' => 'coin', 'item' => 'nim', 'side' => 'sell', 'grams' => 5, 'price_per_gram' => 1000,
        ])->assertSessionHasErrors('grams');

        $this->assertSame(0, TradeRoomOffer::count());
    }

    private function coinHolding(User $u, string $item): float
    {
        $base = Transaction::where('user_id', $u->id)->where('item', $item)->where('status', 'active');
        return round((float) (clone $base)->where('type', 'buy')->sum('quantity') - (float) (clone $base)->where('type', 'sell')->sum('quantity'), 4);
    }
}
