<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Profile', ['user' => $request->user()]);
    }

    public function updateInfo(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name'        => 'required|string|max:100',
            'phone'       => 'required|string|unique:users,phone,' . $user->id,
            'email'       => 'nullable|email',
            'national_id' => 'nullable|string|max:10',
        ]);

        $user->update($request->only('name', 'phone', 'email', 'national_id'));

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'تغییر اطلاعات حساب',
            'body'    => 'اطلاعات پروفایل شما در تاریخ ' . Jalali::now() . ' به‌روز شد.',
            'type'    => 'system',
        ]);

        return back()->with('success', 'اطلاعات با موفقیت ذخیره شد.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if (!Hash::check($request->old_password, $user->password)) {
            return back()->withErrors(['old_password' => 'رمز عبور فعلی اشتباه است.']);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'تغییر رمز عبور',
            'body'    => 'رمز عبور حساب شما در تاریخ ' . Jalali::now() . ' تغییر کرد.',
            'type'    => 'system',
        ]);

        return back()->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
