<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function show()
    {
        $intro = Setting::get('contact_intro', config('page_content.contact.intro'));

        return Inertia::render('Contact', [
            'content' => [
                'title' => Setting::get('contact_title', config('page_content.contact.title')),
                'intro' => $this->removeSupportPhone($intro),
            ],
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
        ]);

        Mail::to('info@metalsp.ir')->send(new ContactMessage($data));

        return back()->with('success', 'پیام شما با موفقیت ارسال شد.');
    }

    private function removeSupportPhone(string $text): string
    {
        $text = str_replace([
            '09936578235',
            '۰۹۹۳۶۵۷۸۲۳۵',
        ], '', $text);

        $text = preg_replace('/برای\s+پشتیبانی\s+با\s+شماره\s+تماس\s+بگیرید\s+یا\s+/u', '', $text) ?? $text;
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
