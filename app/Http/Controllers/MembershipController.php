<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\InviteCode;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembershipController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Membership', ['user' => $request->user()]);
    }

    public function activate(Request $request)
    {
        $user = $request->user();
        if ($user->is_vip) return back()->with('error', 'عضویت ویژه قبلاً فعال شده است.');

        $request->validate(['code' => 'required|string']);

        $invite = InviteCode::where('code', strtoupper(trim($request->code)))
            ->whereNull('used_by')
            ->first();

        if (!$invite) {
            return back()->withErrors(['code' => 'کد وارد شده نامعتبر یا قبلاً استفاده شده است.']);
        }

        $invite->update(['used_by' => $user->id, 'used_at' => now()]);
        $user->update(['is_vip' => true]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => '👑 عضویت ویژه فعال شد',
            'body'    => 'عضویت ویژه‌ی حساب شما در تاریخ ' . Jalali::now() . ' با کد دعوت فعال شد.',
            'type'    => 'promo',
        ]);

        // اطلاع به ادمین‌ها
        User::where('is_admin', true)->each(function ($admin) use ($user) {
            Notification::create([
                'user_id' => $admin->id,
                'title'   => "عضویت ویژه — {$user->name}",
                'body'    => "کاربر {$user->name} ({$user->phone}) در تاریخ " . Jalali::now() . " با کد دعوت عضو ویژه شد.",
                'type'    => 'system',
            ]);
        });

        return back()->with('success', 'تبریک! عضویت ویژه شما فعال شد.');
    }
}
