<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    public function test_contact_page_is_public(): void
    {
        $this->get('/contact')->assertOk();
    }

    public function test_contact_form_sends_email_to_site_inbox(): void
    {
        Mail::fake();

        $response = $this->post('/contact', [
            'name' => 'Test User',
            'email' => 'sender@example.com',
            'subject' => 'Price question',
            'message' => 'Hello from the contact form.',
        ]);

        $response->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo('info@metalsp.ir')
                && $mail->data['email'] === 'sender@example.com'
                && $mail->data['subject'] === 'Price question';
        });
    }
}
