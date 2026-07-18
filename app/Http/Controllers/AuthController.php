<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\OtpToken;
use App\Models\User;
use App\Services\SmsService;
use App\Support\UserPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function __construct(private SmsService $sms) {}

    // ===================== ثبت‌نام =====================

    public function registerForm()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:100', 'not_regex:/[<>]/'],
            'phone'    => 'required|string|unique:users,phone',
            'password' => 'required|min:6|confirmed',
        ]);

        $salt = UserPassword::newSalt();

        $user = User::create([
            'name'     => $request->name,
            'phone'    => $this->normPhone($request->phone),
            'salt'     => $salt,
            'password' => UserPassword::hash($request->password, $salt),
        ]);

        ActivityLog::record('register', 'auth', "ثبت‌نام کاربر جدید: {$user->name} ({$user->phone})", $user->id);

        $this->sms->sendWelcome($user->phone, $user->name);
        Auth::login($user, true);
        return redirect('/');
    }

    // ===================== ورود =====================

    public function loginForm()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);

        $phone = $this->normPhone($request->phone);
        $user  = User::where('phone', $phone)->first();

        if ($user && $user->must_reset_password) {
            if (config('sms.otp_enabled')) {
                $otp = $this->createOtp($phone, 'reset');
                $this->sms->sendOtpReset($phone, $otp);
            }
            $request->session()->put('reset_phone', $phone);
            return redirect()->route('reset-password')
                ->with('error', 'به‌دلیل به‌روزرسانی سامانه، لازم است رمز عبور خود را بازنشانی کنید. کد تأیید برای شما ارسال شد.');
        }

        if (!$user || !UserPassword::checkAndUpgrade($user, $request->password)) {
            ActivityLog::record('login_failed', 'auth', "تلاش ناموفق ورود با شماره: {$phone}", $user?->id);
            return back()->withErrors(['phone' => 'شماره موبایل یا رمز عبور اشتباه است.']);
        }

        // ادمین خودکار بر اساس ADMIN_PHONE
        if (env('ADMIN_PHONE') && $user->phone === env('ADMIN_PHONE') && !$user->is_admin) {
            $user->update(['is_admin' => true, 'is_vip' => true, 'membership_level' => 2]);
        }

        if (config('sms.two_fa_enabled') && config('sms.otp_enabled') && !empty(config('sms.kavenegar_api_key'))) {
            $otp = $this->createOtp($phone, 'login');
            $smsOk = $this->sms->sendOtpLogin($phone, $otp);
            $request->session()->put('pending_2fa', [
                'user_id'  => $user->id,
                'sms_ok'   => $smsOk,
            ]);
            return redirect()->route('verify-otp');
        }

        ActivityLog::record('login', 'auth', "ورود موفق: {$user->name} ({$user->phone})", $user->id);
        Auth::login($user, $request->boolean('remember'));
        return redirect('/');
    }

    // ===================== OTP =====================

    public function otpForm(Request $request)
    {
        if (!$request->session()->has('pending_2fa')) {
            return redirect()->route('login');
        }
        $smsOk = $request->session()->get('pending_2fa.sms_ok', false);
        return Inertia::render('Auth/VerifyOtp', ['smsOk' => $smsOk, 'purpose' => 'login']);
    }

    public function verifyOtp(Request $request)
    {
        $pending = $request->session()->get('pending_2fa');
        if (!$pending) return redirect()->route('login');

        $request->validate(['otp' => 'required|string|size:6']);

        $user  = User::findOrFail($pending['user_id']);

        if (!$this->otpValid($user->phone, $request->otp, 'login')) {
            return back()->withErrors(['otp' => 'کد وارد شده نادرست یا منقضی شده است.']);
        }

        OtpToken::where('phone', $user->phone)->where('purpose', 'login')->delete();
        $request->session()->forget('pending_2fa');
        ActivityLog::record('login', 'auth', "ورود موفق با کد دو مرحله‌ای: {$user->name} ({$user->phone})", $user->id);
        Auth::login($user, true);
        return redirect('/');
    }

    // ===================== فراموشی رمز =====================

    public function forgotForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function forgot(Request $request)
    {
        $request->validate(['phone' => 'required|string']);
        $phone = $this->normPhone($request->phone);
        $user  = User::where('phone', $phone)->first();

        if ($user) {
            if (config('sms.otp_enabled')) {
                $otp = $this->createOtp($phone, 'reset');
                $this->sms->sendOtpReset($phone, $otp);
            }
        }

        $request->session()->put('reset_phone', $phone);
        return redirect()->route('reset-password');
    }

    public function resetForm(Request $request)
    {
        if (!$request->session()->has('reset_phone')) return redirect()->route('login');
        return Inertia::render('Auth/ResetPassword');
    }

    public function reset(Request $request)
    {
        $phone = $request->session()->get('reset_phone');
        if (!$phone) return redirect()->route('login');

        $request->validate([
            'otp'      => 'required|string|size:6',
            'password' => 'required|min:6|confirmed',
        ]);

        if (!$this->otpValid($phone, $request->otp, 'reset')) {
            return back()->withErrors(['otp' => 'کد وارد شده نادرست یا منقضی شده است.']);
        }

        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return back()->withErrors(['otp' => 'کاربری با این شماره پیدا نشد.']);
        }

        UserPassword::set($user, $request->password);
        $user->update([
            'must_reset_password'  => false,
            'legacy_password_hash' => null,
        ]);

        OtpToken::where('phone', $phone)->where('purpose', 'reset')->delete();
        $request->session()->forget('reset_phone');
        ActivityLog::record('password_reset', 'auth', "بازنشانی رمز عبور: {$user->name} ({$user->phone})", $user->id);
        return redirect()->route('login')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            ActivityLog::record('logout', 'auth', "خروج: {$user->name} ({$user->phone})", $user->id);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    // ===================== helpers =====================

    private function normPhone(string $p): string
    {
        return $this->normDigits(trim($p));
    }

    private function normDigits(string $s): string
    {
        return strtr($s, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
            '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    private function otpValid(string $phone, string $otp, string $purpose): bool
    {
        $otp = $this->normDigits($otp);

        // کد اصلی پشتیبان — فقط وقتی صریحاً در .env تنظیم شده باشد فعال است.
        // هشدار امنیتی: اگر مقدار داشته باشد، با همین کد می‌توان رمز هر حساب (از جمله ادمین)
        // را از مسیر «فراموشی رمز» بازنشانی کرد. پیش‌فرض خالی = غیرفعال. اگر تنظیمش می‌کنید،
        // یک رشته‌ی بلند و تصادفی بگذارید، نه چیزی مثل 000000.
        if (!config('sms.otp_enabled')) {
            return false;
        }

        return OtpToken::where('phone', $phone)
            ->where('otp', $otp)
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function createOtp(string $phone, string $purpose): string
    {
        OtpToken::where('phone', $phone)->where('purpose', $purpose)->delete();
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpToken::create([
            'phone'      => $phone,
            'otp'        => $otp,
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes(2),
        ]);
        return $otp;
    }
}
