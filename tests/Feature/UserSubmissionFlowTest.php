<?php

namespace Tests\Feature;

use App\Models\BankCard;
use App\Models\DepositRequest;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\SilverDeliveryRequest;
use App\Models\Ticket;
use App\Models\TradeRoomOffer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSubmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_membership_application_with_recorded_webm_video(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->post('/membership/apply', [
            'national_id_doc' => UploadedFile::fake()->image('national-card.jpg')->size(100),
            'identity_doc' => UploadedFile::fake()->image('business-license.png')->size(100),
            'verification_video' => UploadedFile::fake()->createWithContent(
                'verification.webm',
                $this->minimalWebmContent(),
            ),
            'birth_date' => '1990-01-01',
            'residence_address' => 'مشهد، بازار امام رضا',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('pending', $user->membership_status);
        $this->assertNotNull($user->national_id_doc);
        $this->assertNotNull($user->identity_doc);
        $this->assertNotNull($user->verification_video);
        Storage::disk('public')->assertExists($user->national_id_doc);
        Storage::disk('public')->assertExists($user->identity_doc);
        Storage::disk('public')->assertExists($user->verification_video);
        $this->assertTrue(Notification::where('user_id', $admin->id)->exists());
    }

    public function test_common_user_requests_can_be_submitted(): void
    {
        $user = User::factory()->vip()->create();
        $admin = User::factory()->admin()->create();

        WalletTransaction::create([
            'user_id' => $user->id,
            'amount' => 1_000_000,
            'type' => 'deposit',
            'description' => 'seed',
        ]);
        GoldLedger::create([
            'user_id' => $user->id,
            'grams' => 250,
            'type' => 'admin_adjust',
            'description' => 'seed',
        ]);
        $card = BankCard::create([
            'user_id' => $user->id,
            'bank_name' => 'ملی',
            'card_number' => '6037991234567890',
            'shaba' => 'IR123456789012345678901234',
        ]);

        $this->actingAs($user)->post('/wallet/deposit', [
            'amount' => 500_000,
            'note' => 'رسید پرداخت',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/wallet/withdraw', [
            'amount' => 200_000,
            'bank_card_id' => $card->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/silver-delivery', [
            'metal' => 'gold',
            'purity' => '',
            'grams' => 100,
            'recipient_name' => 'کاربر تست',
            'phone' => '09120000000',
            'delivery_method' => 'address',
            'address' => 'مشهد',
            'postal_code' => '9177945678',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/tickets', [
            'subject' => 'پیگیری',
            'message' => 'لطفا بررسی کنید.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/trade-room', [
            'metal' => 'gold',
            'item' => '',
            'side' => 'sell',
            'purity' => '',
            'grams' => 100,
            'price_per_gram' => 1_000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, DepositRequest::count());
        $this->assertSame(1, WithdrawalRequest::count());
        $this->assertSame(1, SilverDeliveryRequest::count());
        $this->assertSame('9177945678', SilverDeliveryRequest::first()->postal_code);
        $this->assertSame(1, Ticket::count());
        $this->assertSame(1, TradeRoomOffer::count());
        $this->assertTrue(Notification::where('user_id', $admin->id)->exists());
    }

    private function minimalWebmContent(): string
    {
        return base64_decode('GkXfo59ChoEBQveBAULygQRC84EIQoKEd2VibUCho1ZfV1abtTgndW6qgQFOu6lJqYEAAICCSYNCAAPwAABH44O2dcfngQC3iveBAfGCAXXwgQM=', true);
    }
}
