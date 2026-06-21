<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
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

        return Inertia::render('Inventory', [
            'goldBalance'   => $user->goldBalance(),
            'silverBalance' => ['999' => $user->silverBalance('999'), '995' => $user->silverBalance('995')],
            'goldHistory'   => $gold,
            'silverHistory' => $silver,
        ]);
    }
}
