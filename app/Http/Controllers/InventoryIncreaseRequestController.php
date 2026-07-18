<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\InventoryIncreaseRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class InventoryIncreaseRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'metal' => 'required|in:gold,silver',
            'purity' => 'required_if:metal,silver|nullable|in:999,995',
            'grams' => 'required|numeric|min:0.0001|max:1000000',
            'note' => 'nullable|string|max:500',
        ]);

        $metal = $request->metal;
        $purity = $metal === 'silver' ? $request->purity : '';
        $requestRow = InventoryIncreaseRequest::create([
            'user_id' => $request->user()->id,
            'metal' => $metal,
            'purity' => $purity,
            'grams' => $request->grams,
            'note' => $request->note,
        ]);

        $itemLabel = $metal === 'gold' ? 'طلا' : "نقره {$purity}";
        User::where('is_admin', true)->get()->each(function (User $admin) use ($request, $requestRow, $itemLabel) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => "درخواست افزایش موجودی — {$request->user()->name}",
                'body' => "{$requestRow->grams} گرم {$itemLabel} — تاریخ: ".Jalali::now(),
                'type' => 'system',
            ]);
        });

        return back()->with('success', 'درخواست افزایش موجودی برای بررسی مدیریت ثبت شد.');
    }
}
