<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_make_a_vip_member_regular(): void
    {
        $admin = User::factory()->admin()->create();
        $vip = User::factory()->vip()->create();

        $this->actingAs($admin)->post("/admin/set-level/{$vip->id}", [
            'level' => 'regular',
        ])->assertRedirect();

        $vip->refresh();
        $this->assertFalse($vip->is_vip);
        $this->assertFalse($vip->is_admin);
        $this->assertSame(1, $vip->membership_level);
    }

    public function test_admin_level_removes_vip_membership_level_from_a_vip_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $vipAdmin = User::factory()->vip()->admin()->create();

        $this->actingAs($admin)->post("/admin/set-level/{$vipAdmin->id}", [
            'level' => 'admin',
        ])->assertRedirect();

        $vipAdmin->refresh();
        $this->assertFalse($vipAdmin->is_vip);
        $this->assertTrue($vipAdmin->is_admin);
        $this->assertSame(1, $vipAdmin->membership_level);
    }
}
