<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\TradeRoomOffer;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeRoomCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function fund(User $u, int $amount): void
    {
        WalletTransaction::create(['user_id' => $u->id, 'amount' => $amount, 'type' => 'deposit', 'description' => 'x']);
    }

    public function test_accepting_a_sell_offer_splits_the_commission_between_both_parties(): void
    {
        Setting::put('trade_room_commission_percent', 0.1); // ۰.۱٪

        $seller = User::factory()->vip()->create();
        $buyer  = User::factory()->vip()->create();

        // فروشنده ۱۰۰ گرم طلا دارد و پیشنهاد فروش می‌گذارد
        \App\Models\GoldLedger::create(['user_id' => $seller->id, 'grams' => 100, 'type' => 'admin_adjust', 'description' => 'seed']);
        $this->actingAs($seller)->post('/trade-room', [
            'metal' => 'gold', 'side' => 'sell', 'grams' => 100, 'price_per_gram' => 1000,
        ])->assertRedirect();
        $offer = TradeRoomOffer::first();
        $total = 100 * 1000; // 100,000

        // خریدار باید توان پرداخت total + نصف کارمزد را داشته باشد
        $this->fund($buyer, 1_000_000);

        $this->actingAs($buyer)->post("/trade-room/{$offer->id}/accept")->assertRedirect();

        $offer->refresh();
        $fee = (int) round($total * 0.001); // 100
        $buyerFee = intdiv($fee, 2);          // 50
        $sellerFee = $fee - $buyerFee;        // 50

        $this->assertSame('completed', $offer->status);
        $this->assertSame($fee, (int) $offer->commission);

        // خریدار: total + نصف کارمزد کسر شده (از موجودی اولیه‌ی ۱,۰۰۰,۰۰۰)
        $this->assertSame(1_000_000 - ($total + $buyerFee), $buyer->refresh()->walletBalance());
        // فروشنده: total منهای نصف کارمزد دریافت کرده
        $this->assertSame($total - $sellerFee, $seller->refresh()->walletBalance());
        // طلا منتقل شده
        $this->assertSame(100.0, $buyer->goldBalance());
    }

    public function test_admins_are_notified_when_a_trade_room_deal_completes(): void
    {
        Setting::put('trade_room_commission_percent', 0.1);
        $admin  = User::factory()->admin()->create();
        $seller = User::factory()->vip()->create();
        $buyer  = User::factory()->vip()->create();
        \App\Models\GoldLedger::create(['user_id' => $seller->id, 'grams' => 100, 'type' => 'admin_adjust', 'description' => 'seed']);
        $this->fund($buyer, 1_000_000);

        $this->actingAs($seller)->post('/trade-room', ['metal' => 'gold', 'side' => 'sell', 'grams' => 100, 'price_per_gram' => 1000]);
        $offer = TradeRoomOffer::first();
        $this->actingAs($buyer)->post("/trade-room/{$offer->id}/accept")->assertRedirect();

        $this->assertTrue(Notification::where('user_id', $admin->id)->where('title', 'معامله‌ی جدید در اتاق معاملاتی')->exists());
    }

    public function test_accepted_offer_appears_in_the_acceptors_trade_room_history_with_flipped_side(): void
    {
        Setting::put('trade_room_commission_percent', 0);
        $seller = User::factory()->vip()->create();
        $buyer  = User::factory()->vip()->create();
        \App\Models\GoldLedger::create(['user_id' => $seller->id, 'grams' => 100, 'type' => 'admin_adjust', 'description' => 'seed']);
        $this->fund($buyer, 1_000_000);

        $this->actingAs($seller)->post('/trade-room', ['metal' => 'gold', 'side' => 'sell', 'grams' => 100, 'price_per_gram' => 1000]);
        $offer = TradeRoomOffer::first();
        $this->actingAs($buyer)->post("/trade-room/{$offer->id}/accept")->assertRedirect();

        // در تاریخچه‌ی خریدار (پذیرنده) باید نمایش داده شود و نوع آن «خرید» باشد (نه فروشِ پیشنهاد)
        $this->actingAs($buyer)->get('/trade-room')->assertInertia(fn ($page) => $page
            ->has('myOffers', 1)
            ->where('myOffers.0.id', $offer->id)
            ->where('myOffers.0.view_side', 'buy')
            ->where('myOffers.0.role', 'پذیرنده'));
    }

    public function test_admin_can_change_the_commission_percent(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings', ['trade_room_commission_percent' => 0.25])
            ->assertRedirect();

        $this->assertSame('0.25', (string) Setting::get('trade_room_commission_percent'));
    }

    public function test_non_admin_cannot_change_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/settings', ['trade_room_commission_percent' => 5])->assertForbidden();
    }
}
