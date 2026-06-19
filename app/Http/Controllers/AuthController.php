<?php

namespace App\Http\Controllers;

use App\Models\OtpToken;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'name'     => 'required|string|max:100',
            'phone'    => 'required|string|unique:users,phone',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'phone'    => $this->normPhone($request->phone),
            'password' => Hash::make($request->password),
        ]);

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

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['phone' => 'شماره موبایل یا رمز عبور اشتباه است.']);
        }

        // ادمین خودکار بر اساس ADMIN_PHONE
        if (env('ADMIN_PHONE') && $user->phone === env('ADMIN_PHONE') && !$user->is_admin) {
            $user->update(['is_admin' => true, 'is_vip' => true]);
        }

        if (config('sms.two_fa_enabled') && !empty(config('sms.kavenegar_api_key'))) {
            $otp = $this->createOtp($phone, 'login');
            $smsOk = $this->sms->sendOtpLogin($phone, $otp);
            $request->session()->put('pending_2fa', [
                'user_id'  => $user->id,
                'sms_ok'   => $smsOk,
            ]);
            return redirect()->route('verify-otp');
        }

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
        $valid = OtpToken::where('phone', $user->phone)
            ->where('otp', $this->normDigits($request->otp))
            ->where('purpose', 'login')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$valid) {
            return back()->withErrors(['otp' => 'کد وارد شده نادرست یا منقضی شده است.']);
        }

        OtpToken::where('phone', $user->phone)->where('purpose', 'login')->delete();
        $request->session()->forget('pending_2fa');
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
            $otp = $this->createOtp($phone, 'reset');
            $this->sms->sendOtpReset($phone, $otp);
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

        $valid = OtpToken::where('phone', $phone)
            ->where('otp', $this->normDigits($request->otp))
            ->where('purpose', 'reset')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$valid) {
            return back()->withErrors(['otp' => 'کد وارد شده نادرست یا منقضی شده است.']);
        }

        $user = User::where('phone', $phone)->first();
        if ($user) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        OtpToken::where('phone', $phone)->where('purpose', 'reset')->delete();
        $request->session()->forget('reset_phone');
        return redirect()->route('login')->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function logout(Request $request)
    {
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
