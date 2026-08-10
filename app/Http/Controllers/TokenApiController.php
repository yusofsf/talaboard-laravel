<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\SilverLedger;
use App\Models\TradeRoomOffer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PriceService;
use App\Services\TradeRoomExpiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenApiController extends Controller
{
    private const SHOP_ITEMS = [
        'mithqal' => ['label' => 'مثقال طلا', 'group' => 'gold'],
        'geram' => ['label' => 'گرم طلا', 'group' => 'gold'],
        'bahar' => ['label' => 'سکه تمام', 'group' => 'gold'],
        'nim' => ['label' => 'نیم سکه', 'group' => 'gold'],
        'rob' => ['label' => 'ربع سکه', 'group' => 'gold'],
        'mithqal_999' => ['label' => 'مثقال نقره ۹۹۹/۹', 'group' => 'silver'],
        'gram_999' => ['label' => 'گرم نقره ۹۹۹/۹', 'group' => 'silver'],
        'mithqal_995' => ['label' => 'مثقال نقره ۹۹۵', 'group' => 'silver'],
        'gram_995' => ['label' => 'گرم نقره ۹۹۵', 'group' => 'silver'],
    ];

    public function offers(TradeRoomExpiryService $expiry): JsonResponse
    {
        $expiry->expireOpenOffers();

        return response()->json(['data' => TradeRoomOffer::query()->where('status', 'open')->orderByDesc('created_at')->get()
            ->map(fn ($o) => ['id' => $o->id, 'metal' => $o->metal, 'item' => $o->item, 'side' => $o->side, 'purity' => $o->purity, 'grams' => (float) $o->grams, 'price_per_gram' => $o->price_per_gram, 'created_at' => $o->created_at])]);
    }

    public function storeOffer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metal' => 'required|in:gold,silver,coin',
            'side' => 'required|in:buy,sell',
            'item' => 'required_if:metal,coin|nullable|in:bahar,nim,rob',
            'purity' => 'required_if:metal,silver|nullable|in:999,995',
            'grams' => 'required|numeric|min:1|max:1000000',
            'price_per_gram' => 'required|integer|min:1|max:1000000000000',
            'allow_partial_fill' => 'sometimes|boolean',
        ]);
        $user = $request->user();
        abort_unless($user, 403, 'این قابلیت فقط برای توکن متصل به حساب کاربری فعال است.');
        abort_unless($user->isVipMember(), 403, 'فقط کاربران ویژه می‌توانند سفارش اتاق معاملاتی ثبت کنند.');

        $metal = $data['metal'];
        $isCoin = $metal === 'coin';
        $item = $isCoin ? $data['item'] : null;
        $purity = $metal === 'silver' ? $data['purity'] : '';
        $grams = (float) $data['grams'];
        $total = (int) round($grams * $data['price_per_gram']);

        abort_if($isCoin && fmod($grams, 1) !== 0.0, 422, 'تعداد سکه باید عدد صحیح باشد.');
        abort_if(! $isCoin && $grams < TradeRoomOffer::minimumGrams($metal), 422, 'مقدار سفارش از حداقل مجاز کمتر است.');

        $offer = DB::transaction(function () use ($user, $data, $metal, $isCoin, $item, $purity, $grams, $total) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($data['side'] === 'buy') {
                abort_if($lockedUser->walletBalance() < $total, 422, 'موجودی کیف پول کافی نیست.');
            } else {
                $holding = $isCoin
                    ? $this->coinHolding($lockedUser->id, $item)
                    : ($metal === 'gold' ? $lockedUser->goldBalance() : $lockedUser->silverBalance($purity));
                abort_if($holding < $grams, 422, 'موجودی دارایی کافی نیست.');
            }

            $offer = TradeRoomOffer::create([
                'user_id' => $lockedUser->id,
                'source' => 'api_token',
                'metal' => $metal,
                'item' => $item,
                'side' => $data['side'],
                'purity' => $purity,
                'grams' => $grams,
                'price_per_gram' => $data['price_per_gram'],
                'allow_partial_fill' => ! $isCoin && ($data['allow_partial_fill'] ?? true),
                'wallet_reserved_amount' => $data['side'] === 'buy' ? $total : 0,
                'status' => 'open',
            ]);

            if ($data['side'] === 'buy') {
                WalletTransaction::create([
                    'user_id' => $lockedUser->id,
                    'amount' => -$total,
                    'type' => 'withdraw',
                    'description' => "رزرو پیشنهاد خرید API #{$offer->id}",
                ]);
            } elseif (! $isCoin) {
                $ledgerData = [
                    'user_id' => $lockedUser->id,
                    'grams' => -$grams,
                    'type' => 'offer_escrow',
                    'reference_type' => TradeRoomOffer::class,
                    'reference_id' => $offer->id,
                    'description' => "رزرو پیشنهاد فروش API #{$offer->id}",
                ];

                if ($metal === 'gold') {
                    GoldLedger::create($ledgerData);
                } else {
                    SilverLedger::create(['purity' => $purity, ...$ledgerData]);
                }
            }

            return $offer;
        });

        return response()->json(['data' => $offer], 201);
    }

    public function storeShopOrder(Request $request, PriceService $prices): JsonResponse
    {
        abort_unless($request->user(), 403, 'این قابلیت فقط برای توکن متصل به حساب کاربری فعال است.');
        $data = $request->validate([
            'trade_type' => 'required|in:buy,sell',
            'item' => 'required|in:'.implode(',', array_keys(self::SHOP_ITEMS)),
            'quantity' => 'required|numeric|min:0.001|max:1000000',
        ]);

        $meta = self::SHOP_ITEMS[$data['item']];
        $priceKey = $data['trade_type'] === 'buy'
            ? ($meta['group'] === 'gold' ? 'gold' : 'silver')
            : ($meta['group'] === 'gold' ? 'gold_buy' : 'silver_buy');
        $price = data_get($prices->all(), "{$priceKey}.{$data['item']}");
        abort_unless(is_numeric($price) && (float) $price > 0, 422, 'قیمت معتبر در دسترس نیست.');

        $quantity = (float) $data['quantity'];
        $price = (int) round((float) $price);
        $cart = CartItem::create([
            'user_id' => $request->user()->id,
            'trade_type' => $data['trade_type'],
            'item' => $data['item'],
            'item_label' => $meta['label'],
            'item_group' => $meta['group'],
            'quantity' => $quantity,
            'price_per_unit' => $price,
            'total' => (int) round($quantity * $price),
        ]);

        return response()->json(['data' => ['cart_item_id' => $cart->id, 'total' => $cart->total, 'message' => 'سفارش به سبد خرید کاربر افزوده شد؛ برای نهایی‌سازی باید تسویه شود.']], 201);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user(), 403, 'این توکن به حساب کاربری متصل نیست.');

        return response()->json(['data' => $request->user()->only(['id', 'name', 'phone'])]);
    }

    public function wallet(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'این قابلیت فقط برای توکن متصل به حساب کاربری فعال است.');

        return response()->json(['data' => [
            'balance' => $user->walletBalance(),
            'transactions' => $user->walletTransactions()->latest()->limit(50)->get()
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'amount' => (int) $transaction->amount,
                    'type' => $transaction->type,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                ]),
        ]]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'این قابلیت فقط برای توکن متصل به حساب کاربری فعال است.');
        $readIds = NotificationRead::where('user_id', $user->id)->pluck('notification_id');

        return response()->json(['data' => Notification::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhereNull('user_id'))
            ->whereNotIn('id', $readIds)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($notification) => $notification->only(['id', 'title', 'body', 'type', 'created_at']))]);
    }

    public function markAlertRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'این قابلیت فقط برای توکن متصل به حساب کاربری فعال است.');
        Notification::query()
            ->whereKey($id)
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhereNull('user_id'))
            ->firstOrFail();

        NotificationRead::firstOrCreate([
            'notification_id' => $id,
            'user_id' => $user->id,
        ], ['read_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'read' => true]]);
    }

    private function coinHolding(int $userId, string $item): float
    {
        $base = Transaction::query()
            ->where('user_id', $userId)
            ->where('item', $item)
            ->where('status', 'active');

        return round(
            (float) (clone $base)->where('type', 'buy')->sum('quantity')
            - (float) (clone $base)->where('type', 'sell')->sum('quantity'),
            4,
        );
    }
}
