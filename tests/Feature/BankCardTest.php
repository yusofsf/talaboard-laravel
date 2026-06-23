<?php

namespace Tests\Feature;

use App\Models\BankCard;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_and_delete_a_bank_card(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/bank-cards', [
            'bank_name'      => 'ملت',
            'card_number'    => '6037991234567890',
            'account_number' => '123456789',
            'shaba'          => 'IR123456789012345678901234',
        ])->assertRedirect();

        $card = BankCard::first();
        $this->assertSame($user->id, $card->user_id);
        $this->assertSame('6037991234567890', $card->card_number);

        $this->actingAs($user)->delete("/profile/bank-cards/{$card->id}")->assertRedirect();
        $this->assertSame(0, BankCard::count());
    }

    public function test_card_number_must_be_16_digits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/bank-cards', [
            'card_number' => '12345',
            'shaba'       => 'IR123456789012345678901234',
        ])->assertSessionHasErrors('card_number');

        $this->assertSame(0, BankCard::count());
    }

    public function test_user_cannot_delete_another_users_bank_card(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $card = BankCard::create(['user_id' => $owner->id, 'card_number' => '6037991234567890', 'shaba' => 'IR1']);

        $this->actingAs($other)->delete("/profile/bank-cards/{$card->id}")->assertRedirect();

        $this->assertSame(1, BankCard::count());
    }

    public function test_withdrawal_request_uses_the_selected_bank_cards_details(): void
    {
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 1_000_000, 'type' => 'deposit', 'description' => 'x']);
        $card = BankCard::create([
            'user_id' => $user->id, 'bank_name' => 'ملی', 'card_number' => '6037991234567890', 'shaba' => 'IR999',
        ]);

        $this->actingAs($user)->post('/wallet/withdraw', [
            'amount'       => 500000,
            'bank_card_id' => $card->id,
        ])->assertRedirect();

        $withdrawal = WithdrawalRequest::first();
        $this->assertSame('6037991234567890', $withdrawal->card_number);
        $this->assertSame('IR999', $withdrawal->shaba);
    }

    public function test_user_cannot_withdraw_using_another_users_bank_card(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 1_000_000, 'type' => 'deposit', 'description' => 'x']);
        $card = BankCard::create(['user_id' => $other->id, 'card_number' => '6037991234567890', 'shaba' => 'IR999']);

        $this->actingAs($user)->post('/wallet/withdraw', [
            'amount'       => 500000,
            'bank_card_id' => $card->id,
        ])->assertSessionHasErrors('bank_card_id');

        $this->assertSame(0, WithdrawalRequest::count());
    }
}
