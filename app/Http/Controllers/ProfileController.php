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
        return Inertia::render('Profile', [
            'user'      => $request->user(),
            'bankCards' => $request->user()->bankCards()->get(['id', 'bank_name', 'card_number', 'account_number', 'shaba']),
        ]);
    }

    public function storeBankCard(Request $request)
    {
        $request->validate([
            'bank_name'      => 'nullable|string|max:50',
            'card_number'    => 'required|digits:16',
            'account_number' => 'nullable|string|max:30',
            'shaba'          => 'required|string|max:30',
        ]);

        $request->user()->bankCards()->create($request->only('bank_name', 'card_number', 'account_number', 'shaba'));

        return back()->with('success', 'کارت بانکی اضافه شد.');
    }

    public function destroyBankCard(Request $request, int $id)
    {
        $request->user()->bankCards()->where('id', $id)->delete();

        return back()->with('success', 'کارت بانکی حذف شد.');
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
