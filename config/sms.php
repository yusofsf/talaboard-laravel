<?php

return [
    // کلید موقت برای غیرفعال‌کردن کامل سامانه‌ی پیامکی بدون حذف کلید API (پیش‌فرض فعال است)
    'enabled'                 => env('SMS_ENABLED', true),
    'master_otp'              => env('MASTER_OTP', ''),
    'master_otp_enabled'      => env('MASTER_OTP_ENABLED', false),
    'master_otp_help_enabled' => env('MASTER_OTP_HELP_ENABLED', false),
    'kavenegar_api_key'       => env('KAVENEGAR_API_KEY'),
    'kavenegar_sender'        => env('KAVENEGAR_SENDER'),
    'kavenegar_otp_template'  => env('KAVENEGAR_OTP_TEMPLATE', 'verify'),
    'kavenegar_reset_template'=> env('KAVENEGAR_RESET_TEMPLATE', ''),
    'two_fa_enabled'          => env('TWO_FA_ENABLED', false),
];
