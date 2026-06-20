<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Notification;
use App\Models\SilverDeliveryRequest;
use App\Models\SilverLedger;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SilverDeliveryController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $requests = SilverDeliveryRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'purity'     => $r->purity,
                'grams'      => (float) $r->grams,
                'status'     => $r->status,
                'admin_note' => $r->admin_note,
                'created_at' => Jalali::format($r->created_at),
            ]);

        return Inertia::render('SilverDelivery', [
            'requests'      => $requests,
            'silverBalance' => [
                '999' => $user->silverBalance('999'),
                '995' => $user->silverBalance('995'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'purity'         => 'required|in:999,995',
            'grams'          => 'required|numeric|min:1',
            'recipient_name' => 'required|string|max:100',
            'phone'          => 'required|string|max:20',
            'address'        => 'required|string|max:500',
        ]);

        $grams = (float) $request->grams;
        if ($user->silverBalance($request->purity) < $grams) {
            return back()->withErrors(['grams' => 'موجودی نقره‌ی شما برای این عیار کافی نیست.']);
        }

        $admins = User::where('is_admin', true)->get();

        DB::transaction(function () use ($user, $request, $grams, $admins) {
            $delivery = SilverDeliveryRequest::create([
                'user_id'        => $user->id,
                'purity'         => $request->purity,
                'grams'          => $grams,
                'recipient_name' => $request->recipient_name,
                'phone'          => $request->phone,
                'address'        => $request->address,
                'status'         => 'pending',
            ]);

            SilverLedger::create([
                'user_id' => $user->id, 'purity' => $request->purity, 'grams' => -$grams,
                'type' => 'delivery', 'reference_type' => SilverDeliveryRequest::class, 'reference_id' => $delivery->id,
                'description' => "درخواست تحویل فیزیکی #{$delivery->id}",
            ]);

            Notification::create([
                'user_id' => $user->id,
                'title'   => 'درخواست تحویل فیزیکی ثبت شد',
                'body'    => "{$grams} گرم نقره {$delivery->purity} — تاریخ: " . Jalali::now() . ' — در حال بررسی.',
                'type'    => 'system',
            ]);

            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title'   => "درخواست تحویل فیزیکی نقره — {$user->name}",
                    'body'    => "{$grams} گرم نقره {$delivery->purity} — تاریخ: " . Jalali::now(),
                    'type'    => 'system',
                ]);
            }
        });

        try {
            $this->sms->sendDeliveryRequestUser($user->phone, $user->name, $grams, $request->purity);
            foreach ($admins as $admin) {
                $this->sms->sendDeliveryRequestAdmin($admin->phone, $user->name, $grams, $request->purity);
            }
        } catch (\Exception) {}

        return back()->with('success', 'درخواست تحویل فیزیکی شما ثبت شد.');
    }
}
