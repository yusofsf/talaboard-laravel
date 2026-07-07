<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageContentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_about_and_contact_page_content(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/settings', [
            'trade_room_commission_percent' => '0.2',
            'about_title' => 'درباره فروشگاه تست',
            'about_body' => "پاراگراف اول درباره ما\n\nپاراگراف دوم درباره ما",
            'contact_title' => 'تماس با تیم تست',
            'contact_intro' => 'برای پیگیری سفارش با ما در تماس باشید.',
        ])->assertRedirect();

        $this->assertSame('درباره فروشگاه تست', Setting::get('about_title'));
        $this->assertSame("پاراگراف اول درباره ما\n\nپاراگراف دوم درباره ما", Setting::get('about_body'));
        $this->assertSame('تماس با تیم تست', Setting::get('contact_title'));
        $this->assertSame('برای پیگیری سفارش با ما در تماس باشید.', Setting::get('contact_intro'));
    }

    public function test_public_about_and_contact_pages_use_saved_content(): void
    {
        Setting::put('about_title', 'عنوان درباره ذخیره‌شده');
        Setting::put('about_body', 'متن درباره ذخیره‌شده');
        Setting::put('contact_title', 'عنوان تماس ذخیره‌شده');
        Setting::put('contact_intro', 'متن تماس ذخیره‌شده');

        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('About')
                ->where('content.title', 'عنوان درباره ذخیره‌شده')
                ->where('content.body', 'متن درباره ذخیره‌شده'));

        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contact')
                ->where('content.title', 'عنوان تماس ذخیره‌شده')
                ->where('content.intro', 'متن تماس ذخیره‌شده'));
    }
}
