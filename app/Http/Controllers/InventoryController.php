<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\SilverDeliveryRequest;
use App\Models\InventoryIncreaseRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $gold = $user->goldLedger()->limit(100)->get()->map(fn ($l) => [
            'id' => $l->id, 'grams' => (float) $l->grams, 'type' => $l->type,
            'description' => $l->description, 'created_at' => Jalali::format($l->created_at),
        ]);

        $silver = $user->silverLedger()->limit(100)->get()->map(fn ($l) => [
            'id' => $l->id, 'purity' => $l->purity, 'grams' => (float) $l->grams, 'type' => $l->type,
            'description' => $l->description, 'created_at' => Jalali::format($l->created_at),
        ]);

        $deliveryRequests = SilverDeliveryRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'metal'      => $r->metal,
                'purity'     => $r->purity,
                'grams'      => (float) $r->grams,
                'delivery_method' => $r->delivery_method ?? 'address',
                'address'     => $r->address,
                'postal_code' => $r->postal_code,
                'status'     => $r->status,
                'admin_note' => $r->admin_note,
                'created_at' => Jalali::format($r->created_at),
            ]);

        $inventoryIncreaseRequests = InventoryIncreaseRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'metal' => $r->metal,
                'purity' => $r->purity,
                'grams' => (float) $r->grams,
                'note' => $r->note,
                'status' => $r->status,
                'admin_note' => $r->admin_note,
                'created_at' => Jalali::format($r->created_at),
            ]);

        return Inertia::render('Inventory', [
            'goldBalance'      => $user->goldBalance(),
            'silverBalance'    => ['999' => $user->silverBalance('999'), '995' => $user->silverBalance('995')],
            'goldHistory'      => $gold,
            'silverHistory'    => $silver,
            'deliveryRequests' => $deliveryRequests,
            'inventoryIncreaseRequests' => $inventoryIncreaseRequests,
        ]);
    }
}
