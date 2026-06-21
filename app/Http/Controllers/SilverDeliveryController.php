<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\SilverDeliveryRequest;
use App\Models\SilverLedger;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SilverDeliveryController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'metal'          => 'required|in:gold,silver',
            'purity'         => 'required_if:metal,silver|in:999,995',
            'grams'          => 'required|numeric|min:1',
            'recipient_name' => 'required|string|max:100',
            'phone'          => 'required|string|max:20',
            'address'        => 'required|string|max:500',
        ]);

        $metal  = $request->metal;
        $purity = $metal === 'silver' ? $request->purity : '';
        $grams  = (float) $request->grams;

        $balance = $metal === 'gold' ? $user->goldBalance() : $user->silverBalance($purity);
        if ($balance < $grams) {
            return back()->withErrors(['grams' => 'موجودی شما برای این مورد کافی نیست.']);
        }

        $admins = User::where('is_admin', true)->get();
        $itemLabel = $metal === 'gold' ? 'طلا' : "نقره {$purity}";

        DB::transaction(function () use ($user, $request, $metal, $purity, $grams, $admins, $itemLabel) {
            $delivery = SilverDeliveryRequest::create([
                'user_id'        => $user->id,
                'metal'          => $metal,
                'purity'         => $purity,
                'grams'          => $grams,
                'recipient_name' => $request->recipient_name,
                'phone'          => $request->phone,
                'address'        => $request->address,
                'status'         => 'pending',
            ]);

            if ($metal === 'gold') {
                GoldLedger::create([
                    'user_id' => $user->id, 'grams' => -$grams,
                    'type' => 'delivery', 'reference_type' => SilverDeliveryRequest::class, 'reference_id' => $delivery->id,
                    'description' => "درخواست تحویل فیزیکی #{$delivery->id}",
                ]);
            } else {
                SilverLedger::create([
                    'user_id' => $user->id, 'purity' => $purity, 'grams' => -$grams,
                    'type' => 'delivery', 'reference_type' => SilverDeliveryRequest::class, 'reference_id' => $delivery->id,
                    'description' => "درخواست تحویل فیزیکی #{$delivery->id}",
                ]);
            }

            Notification::create([
                'user_id' => $user->id,
                'title'   => 'درخواست تحویل فیزیکی ثبت شد',
                'body'    => "{$grams} گرم {$itemLabel} — تاریخ: " . Jalali::now() . ' — در حال بررسی.',
                'type'    => 'system',
            ]);

            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title'   => "درخواست تحویل فیزیکی — {$user->name}",
                    'body'    => "{$grams} گرم {$itemLabel} — تاریخ: " . Jalali::now(),
                    'type'    => 'system',
                ]);
            }
        });

        try {
            $this->sms->send($user->phone, "درخواست تحویل فیزیکی {$grams} گرم {$itemLabel} شما ثبت شد و در حال بررسی است.");
            foreach ($admins as $admin) {
                $this->sms->send($admin->phone, "درخواست تحویل فیزیکی جدید: {$user->name} — {$grams} گرم {$itemLabel}.");
            }
        } catch (\Exception) {}

        return back()->with('success', 'درخواست تحویل فیزیکی شما ثبت شد.');
    }
}
