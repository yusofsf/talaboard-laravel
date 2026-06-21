<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private ?string $apiKey;
    private ?string $sender;
    private string  $otpTemplate;

    public function __construct()
    {
        $this->apiKey      = config('sms.kavenegar_api_key');
        $this->sender      = config('sms.kavenegar_sender');
        $this->otpTemplate = config('sms.kavenegar_otp_template', 'verify');
    }

    private function enabled(): bool
    {
        return !empty($this->apiKey);
    }

    public function sendOtpLogin(string $phone, string $otp): bool
    {
        return $this->sendLookup($phone, $otp, $this->otpTemplate);
    }

    public function sendOtpReset(string $phone, string $otp): bool
    {
        $template = config('sms.kavenegar_reset_template') ?: $this->otpTemplate;
        return $this->sendLookup($phone, $otp, $template);
    }

    public function sendWelcome(string $phone, string $name): bool
    {
        return $this->sendText($phone, "سلام {$name} عزیز، ثبت‌نام شما در آبشده صفرپور با موفقیت انجام شد. 🌟");
    }

    public function sendTradeConfirm(string $phone, string $name, string $type, string $item, float $qty, int $total): bool
    {
        $typeLabel = $type === 'buy' ? 'خرید' : 'فروش';
        $msg = "کاربر گرامی {$name}، {$typeLabel} {$qty} گرم {$item} به مبلغ " . number_format($total) . " تومان ثبت شد.";
        return $this->sendText($phone, $msg);
    }

    public function sendDeliveryRequestUser(string $phone, string $name, float $grams, string $purity): bool
    {
        $msg = "کاربر گرامی {$name}، درخواست تحویل فیزیکی {$grams} گرم نقره {$purity} شما ثبت شد و در حال بررسی است.";
        return $this->sendText($phone, $msg);
    }

    public function sendDeliveryRequestAdmin(string $phone, string $userName, float $grams, string $purity): bool
    {
        $msg = "درخواست تحویل فیزیکی جدید: {$userName} — {$grams} گرم نقره {$purity}.";
        return $this->sendText($phone, $msg);
    }

    public function sendDeliveryStatusUpdate(string $phone, string $name, string $statusLabel): bool
    {
        $msg = "کاربر گرامی {$name}، وضعیت درخواست تحویل فیزیکی نقره‌ی شما: {$statusLabel}";
        return $this->sendText($phone, $msg);
    }

    /** ارسال پیامک متنی دلخواه. */
    public function send(string $phone, string $message): bool
    {
        return $this->sendText($phone, $message);
    }

    private function sendLookup(string $phone, string $token, string $template): bool
    {
        if (!$this->enabled()) return false;
        try {
            $response = Http::get("https://api.kavenegar.com/v1/{$this->apiKey}/verify/lookup.json", [
                'receptor' => $phone,
                'token'    => $token,
                'template' => $template,
            ]);
            $status = $response->json('return.status');
            if ($status === 200) {
                Log::channel('single')->info("SMS [otp] sent to {$phone}");
                return true;
            }
            Log::channel('single')->warning("SMS [otp-fail] {$phone}: status={$status}");
            return false;
        } catch (\Exception $e) {
            Log::channel('single')->error("SMS [otp-fail] {$phone}: " . $e->getMessage());
            return false;
        }
    }

    private function sendText(string $phone, string $message): bool
    {
        if (!$this->enabled() || !$this->sender) return false;
        try {
            $response = Http::get("https://api.kavenegar.com/v1/{$this->apiKey}/sms/send.json", [
                'receptor' => $phone,
                'sender'   => $this->sender,
                'message'  => $message,
            ]);
            $status = $response->json('return.status');
            if ($status === 200) {
                Log::channel('single')->info("SMS [sent] to {$phone}");
                return true;
            }
            Log::channel('single')->warning("SMS [fail] {$phone}: status={$status}");
            return false;
        } catch (\Exception $e) {
            Log::channel('single')->error("SMS [fail] {$phone}: " . $e->getMessage());
            return false;
        }
    }
}
