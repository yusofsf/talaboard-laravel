<?php

namespace Tests\Feature;

use App\Models\GoldLedger;
use App\Models\SilverLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_only_their_own_accounting_ledgers(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 120000, 'type' => 'deposit']);
        WalletTransaction::create(['user_id' => $other->id, 'amount' => 900000, 'type' => 'deposit']);
        GoldLedger::create(['user_id' => $user->id, 'grams' => 2.5, 'type' => 'purchase']);
        SilverLedger::create(['user_id' => $user->id, 'purity' => '999', 'grams' => 4, 'type' => 'purchase']);
        Transaction::create(['user_id' => $user->id, 'type' => 'buy', 'item' => 'geram', 'item_label' => 'طلا (گرم)', 'quantity' => 2.5, 'price_per_unit' => 1000000, 'total' => 2500000, 'status' => 'active']);

        $this->actingAs($user)->get('/accounting')->assertInertia(fn ($page) => $page
            ->component('Accounting')
            ->where('balances.cash', 120000)
            ->where('balances.gold', 2.5)
            ->has('cashTransactions', 1)
            ->has('assetTransactions', 2)
            ->has('tradeSummary', 1)
            ->where('tradeSummary.0.label', 'طلا (گرم)')
            ->where('tradeSummary.0.buy_total', 2500000));
    }

    public function test_admin_sees_the_consolidated_accounting_report(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        WalletTransaction::create(['user_id' => $user->id, 'amount' => 125000, 'type' => 'deposit']);

        $this->actingAs($admin)->get('/admin/accounting')->assertInertia(fn ($page) => $page
            ->component('Admin/Accounting')
            ->where('summary.cash_balance', 125000)
            ->has('walletTransactions', 1)
            ->has('userBalances', 2));
    }

    public function test_non_admin_cannot_view_the_management_accounting_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/accounting')->assertForbidden();
    }
}
