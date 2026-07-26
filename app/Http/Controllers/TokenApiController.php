<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\TradeRoomOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenApiController extends Controller
{
    public function offers(): JsonResponse
    {
        return response()->json(['data' => TradeRoomOffer::query()->where('status', 'open')->orderByDesc('created_at')->get()
            ->map(fn ($o) => ['id' => $o->id, 'metal' => $o->metal, 'item' => $o->item, 'side' => $o->side, 'purity' => $o->purity, 'grams' => (float) $o->grams, 'price_per_gram' => $o->price_per_gram, 'created_at' => $o->created_at])]);
    }

    public function storeOffer(Request $request): JsonResponse
    {
        $request->validate(['metal' => 'required|in:gold,silver,coin', 'side' => 'required|in:buy,sell', 'item' => 'nullable|in:bahar,nim,rob', 'purity' => 'nullable|in:999,995', 'grams' => 'required|numeric|min:1', 'price_per_gram' => 'required|integer|min:1']);
        $user = $request->user();
        abort_unless($user->isVipMember(), 403, 'فقط کاربران ویژه می‌توانند سفارش اتاق معاملاتی ثبت کنند.');
        if ($request->metal !== 'coin' && (float) $request->grams < 100) {
            abort(422, 'حداقل سفارش اتاق معاملاتی ۱۰۰ گرم است.');
        }
        $offer = TradeRoomOffer::create(['user_id' => $user->id, 'metal' => $request->metal, 'item' => $request->metal === 'coin' ? $request->item : null, 'side' => $request->side, 'purity' => $request->metal === 'silver' ? $request->purity : '', 'grams' => $request->grams, 'price_per_gram' => $request->price_per_gram, 'status' => 'open']);

        return response()->json(['data' => $offer], 201);
    }

    public function storeShopOrder(Request $request): JsonResponse
    {
        $request->validate(['trade_type' => 'required|in:buy,sell', 'item' => 'required|in:mithqal,geram,bahar,nim,rob,mithqal_999,gram_999,mithqal_995,gram_995', 'quantity' => 'required|numeric|min:0.001', 'price_per_unit' => 'required|integer|min:1']);
        $item = $request->item;
        $silver = str_contains($item, '_99');
        $label = ['mithqal' => 'مثقال طلا', 'geram' => 'گرم طلا', 'bahar' => 'سکه تمام', 'nim' => 'نیم سکه', 'rob' => 'ربع سکه', 'mithqal_999' => 'مثقال نقره ۹۹۹', 'gram_999' => 'گرم نقره ۹۹۹', 'mithqal_995' => 'مثقال نقره ۹۹۵', 'gram_995' => 'گرم نقره ۹۹۵'][$item];
        $cart = CartItem::create(['user_id' => $request->user()->id, 'trade_type' => $request->trade_type, 'item' => $item, 'item_label' => $label, 'item_group' => $silver ? 'silver' : 'gold', 'quantity' => $request->quantity, 'price_per_unit' => $request->price_per_unit, 'total' => (int) round($request->quantity * $request->price_per_unit)]);

        return response()->json(['data' => ['cart_item_id' => $cart->id, 'total' => $cart->total, 'message' => 'سفارش به سبد خرید کاربر افزوده شد؛ برای نهایی‌سازی باید تسویه شود.']], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->only(['id', 'name', 'phone'])]);
    }
}
