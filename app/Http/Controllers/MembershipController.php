<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MembershipController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Membership', ['user' => $request->user()]);
    }

    public function apply(Request $request)
    {
        $user = $request->user();
        if ($user->is_vip) return back()->with('error', 'عضویت ویژه قبلاً فعال شده است.');
        if ($user->membership_status === 'pending') {
            return back()->with('error', 'درخواست قبلی شما هنوز در حال بررسی است.');
        }

        $request->validate([
            'national_id_doc'    => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'identity_doc'       => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'verification_video' => 'required|file|mimes:mp4,mov,avi,webm|max:5120',
            'birth_date'         => 'required|date',
            'residence_address'  => 'required|string|max:500',
        ]);

        $dir = "membership/{$user->id}";

        // حذف فایل‌های درخواست قبلی (در صورت رد شدن و ارسال دوباره)
        foreach ([$user->national_id_doc, $user->identity_doc, $user->verification_video] as $old) {
            if ($old) {
                Storage::disk('local')->delete($old);
                Storage::disk('public')->delete($old);
            }
        }

        $user->update([
            'national_id_doc'    => $request->file('national_id_doc')->store($dir, 'local'),
            'identity_doc'       => $request->file('identity_doc')->store($dir, 'local'),
            'verification_video' => $request->file('verification_video')->store($dir, 'local'),
            'birth_date'         => $request->birth_date,
            'residence_address'  => $request->residence_address,
            'membership_status'  => 'pending',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'درخواست عضویت ویژه ثبت شد',
            'body'    => 'درخواست شما در تاریخ ' . Jalali::now() . ' ثبت شد و در حال بررسی است.',
            'type'    => 'system',
        ]);

        ActivityLog::record('membership_apply', 'membership', "ثبت درخواست عضویت ویژه — کاربر: {$user->name} ({$user->phone})", $user->id);

        User::where('is_admin', true)->each(function ($admin) use ($user) {
            Notification::create([
                'user_id' => $admin->id,
                'title'   => "درخواست عضویت ویژه — {$user->name}",
                'body'    => "کاربر {$user->name} ({$user->phone}) درخواست عضویت ویژه ثبت کرد. تاریخ: " . Jalali::now(),
                'type'    => 'system',
            ]);
        });

        return back()->with('success', 'درخواست شما با موفقیت ثبت شد و در حال بررسی است.');
    }
}
