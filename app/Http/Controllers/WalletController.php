<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $txns = $user->walletTransactions()->get()->map(fn($t) => [
            'id'          => $t->id,
            'amount'      => $t->amount,
            'type'        => $t->type,
            'description' => $t->description,
            'created_at'  => Jalali::format($t->created_at),
        ]);

        return Inertia::render('Wallet', [
            'balance' => $user->walletBalance(),
            'txns'    => $txns,
        ]);
    }
}
