<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_increasing_a_users_inventory_notifies_other_admins_with_the_acting_admins_name(): void
    {
        $actingAdmin = User::factory()->admin()->create(['name' => 'مدیر اول']);
        $otherAdmin  = User::factory()->admin()->create(['name' => 'مدیر دوم']);
        $user        = User::factory()->create();

        $this->actingAs($actingAdmin)->post("/admin/inventory-adjust/{$user->id}", [
            'metal' => 'gold', 'grams' => 5,
        ])->assertRedirect();

        $notif = Notification::where('user_id', $otherAdmin->id)->where('title', 'تغییر موجودی انبار توسط ادمین')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('مدیر اول', $notif->body);
        $this->assertStringContainsString('افزایش', $notif->body);

        // به خود کاربر هم نوتیف می‌رسد، اما بدون نام ادمین
        $userNotif = Notification::where('user_id', $user->id)->first();
        $this->assertStringNotContainsString('مدیر اول', $userNotif->body);
    }
}
