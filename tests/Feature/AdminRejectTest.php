<?php

namespace Tests\Feature;

use App\Models\GoldLedger;
use App\Models\Transaction;
use App\Models\TradeRoomOffer;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRejectTest extends TestCase
{
    use RefreshDatabase;

    /** رد معامله‌ی فروشگاه: کیف پول و موجودی طلا کاملاً برگشت می‌خورد و وضعیت «رد شده» می‌شود. */
    public function test_admin_reject_shop_buy_reverses_wallet_and_gold(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        // شبیه‌سازی یک خرید ۱۰ گرم طلا که قبلاً ثبت شده
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 600_000_000, 'type' => 'deposit', 'description' => 'charge']);
        WalletTransaction::create(['user_id' => $user->id, 'amount' => -500_000_000, 'type' => 'withdraw', 'description' => 'buy']);
        GoldLedger::create(['user_id' => $user->id, 'grams' => 10, 'type' => 'purchase', 'description' => 'buy']);
        $txn = Transaction::create([
            'user_id' => $user->id, 'type' => 'buy', 'item' => 'geram', 'item_label' => 'گرم طلا',
            'quantity' => 10, 'price_per_unit' => 50_000_000, 'total' => 500_000_000, 'status' => 'active',
        ]);

        $this->assertSame(100_000_000, $user->walletBalance());
        $this->assertEqualsWithDelta(10.0, $user->goldBalance(), 0.0001);

        $this->actingAs($admin)->post("/admin/transactions/{$txn->id}/reject", ['reason' => 'مدارک ناقص'])
            ->assertRedirect();

        $txn->refresh();
        $this->assertSame('rejected', $txn->status);
        $this->assertSame('مدارک ناقص', $txn->admin_note);
        $this->assertSame(600_000_000, $user->refresh()->walletBalance()); // پول برگشت
        $this->assertEqualsWithDelta(0.0, $user->goldBalance(), 0.0001);    // طلا برگشت
    }

    public function test_admin_reject_requires_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();
        $txn = Transaction::create([
            'user_id' => $user->id, 'type' => 'buy', 'item' => 'geram', 'item_label' => 'گرم طلا',
            'quantity' => 10, 'price_per_unit' => 50_000_000, 'total' => 500_000_000, 'status' => 'active',
        ]);

        $this->actingAs($admin)->post("/admin/transactions/{$txn->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame('active', $txn->refresh()->status);
    }

    public function test_non_admin_cannot_reject(): void
    {
        $user = User::factory()->create();
        $txn = Transaction::create([
            'user_id' => $user->id, 'type' => 'buy', 'item' => 'geram', 'item_label' => 'گرم طلا',
            'quantity' => 10, 'price_per_unit' => 50_000_000, 'total' => 500_000_000, 'status' => 'active',
        ]);

        $this->actingAs($user)->post("/admin/transactions/{$txn->id}/reject", ['reason' => 'x'])
            ->assertForbidden();
        $this->assertSame('active', $txn->refresh()->status);
    }

    /** رد معامله‌ی اتاق معاملاتی (فروش طلا): پول و طلای هر دو طرف معکوس می‌شود. */
    public function test_admin_reject_trade_room_sell_reverses_both_parties(): void
    {
        $admin  = User::factory()->admin()->create();
        $seller = User::factory()->vip()->create();
        $buyer  = User::factory()->vip()->create();

        // وضعیت پس از تکمیل یک معامله‌ی فروش ۱۰۰ گرم طلا به قیمت هر گرم ۵۰ میلیون = ۵ میلیارد
        $grams = 100; $total = 5_000_000_000;
        // فروشنده: طلا را در زمان ثبت پیشنهاد رزرو کرده (‎-۱۰۰‎) و پول را دریافت کرده (‎+total)
        GoldLedger::create(['user_id' => $seller->id, 'grams' => -$grams, 'type' => 'offer_escrow', 'description' => 'escrow']);
        WalletTransaction::create(['user_id' => $seller->id, 'amount' => $total, 'type' => 'deposit', 'description' => 'sale']);
        // خریدار: پول پرداخت کرده (‎-total‎) و طلا گرفته (‎+۱۰۰‎)
        WalletTransaction::create(['user_id' => $buyer->id, 'amount' => 9_000_000_000, 'type' => 'deposit', 'description' => 'charge']);
        WalletTransaction::create(['user_id' => $buyer->id, 'amount' => -$total, 'type' => 'withdraw', 'description' => 'buy']);
        GoldLedger::create(['user_id' => $buyer->id, 'grams' => $grams, 'type' => 'p2p_buy', 'description' => 'buy']);

        $offer = TradeRoomOffer::create([
            'user_id' => $seller->id, 'metal' => 'gold', 'side' => 'sell', 'purity' => '',
            'grams' => $grams, 'price_per_gram' => 50_000_000, 'status' => 'completed',
            'counterparty_id' => $buyer->id, 'completed_at' => now(),
        ]);

        // پیش از رد
        $this->assertSame($total, $seller->walletBalance());
        $this->assertEqualsWithDelta(-100.0, $seller->goldBalance(), 0.0001);
        $this->assertSame(4_000_000_000, $buyer->walletBalance());
        $this->assertEqualsWithDelta(100.0, $buyer->goldBalance(), 0.0001);

        $this->actingAs($admin)->post("/admin/trade-room/{$offer->id}/reject", ['reason' => 'تخلف'])
            ->assertRedirect();

        $offer->refresh();
        $this->assertSame('cancelled', $offer->status);
        $this->assertSame('تخلف', $offer->admin_note);
        // فروشنده: پول پس داده شد (۰)، طلای رزرو برگشت (۰)
        $this->assertSame(0, $seller->refresh()->walletBalance());
        $this->assertEqualsWithDelta(0.0, $seller->goldBalance(), 0.0001);
        // خریدار: پول برگشت (۹ میلیارد)، طلا پس گرفته شد (۰)
        $this->assertSame(9_000_000_000, $buyer->refresh()->walletBalance());
        $this->assertEqualsWithDelta(0.0, $buyer->goldBalance(), 0.0001);
    }
}
