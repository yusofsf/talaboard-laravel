<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkCode;
use App\Models\InventoryIncreaseRequest;
use App\Models\Notification;
use App\Models\DepositRequest;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Models\GoldLedger;
use App\Models\ActivityLog;
use App\Services\PriceService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramMembershipController extends Controller
{
    private function authorize(Request $request): void
    {
        $token = (string) config('services.telegram.link_api_token');

        abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401);
    }

    public function link(Request $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'size:24', 'alpha_num'],
            'telegram_chat_id' => ['required', 'string', 'max:32'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $code = TelegramLinkCode::query()
                ->where('code_hash', hash('sha256', strtoupper($data['code'])))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            abort_unless($code, 422, 'کد اتصال نامعتبر یا منقضی شده است.');
            $user = User::query()->lockForUpdate()->findOrFail($code->user_id);
            abort_unless($user->isVipMember(), 403, 'عضویت ویژه این حساب فعال نیست.');

            $conflict = User::query()
                ->where('telegram_chat_id', $data['telegram_chat_id'])
                ->whereKeyNot($user->id)
                ->exists();
            abort_if($conflict, 422, 'این حساب تلگرام به کاربر دیگری متصل است.');

            $user->update(['telegram_chat_id' => $data['telegram_chat_id']]);
            $code->update(['used_at' => now()]);

            return $user;
        });

        return response()->json($this->membershipPayload($user));
    }

    public function member(Request $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate(['telegram_chat_id' => ['required', 'string', 'max:32']]);
        $user = User::query()->where('telegram_chat_id', $data['telegram_chat_id'])->first();

        if (! $user || ! $user->isVipMember()) {
            return response()->json(['linked' => false, 'vip' => false], 403);
        }

        return response()->json($this->membershipPayload($user));
    }

    public function inventoryIncrease(Request $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'telegram_chat_id' => ['required', 'string', 'max:32'],
            'item' => ['required', 'in:gold,silver_999,silver_995,full_coin,half_coin,quarter_coin'],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::query()->where('telegram_chat_id', $data['telegram_chat_id'])->first();
        abort_unless($user && $user->isVipMember(), 403, 'عضویت ویژه فعال نیست.');

        $items = [
            'gold' => ['metal' => 'gold', 'purity' => '', 'label' => 'طلا', 'unit' => 'گرم'],
            'silver_999' => ['metal' => 'silver', 'purity' => '999', 'label' => 'نقره ۹۹۹/۹', 'unit' => 'گرم'],
            'silver_995' => ['metal' => 'silver', 'purity' => '995', 'label' => 'نقره ۹۹۵', 'unit' => 'گرم'],
            'full_coin' => ['metal' => 'coin', 'purity' => 'full', 'label' => 'سکه تمام', 'unit' => 'عدد'],
            'half_coin' => ['metal' => 'coin', 'purity' => 'half', 'label' => 'نیم‌سکه', 'unit' => 'عدد'],
            'quarter_coin' => ['metal' => 'coin', 'purity' => 'quarter', 'label' => 'ربع‌سکه', 'unit' => 'عدد'],
        ];
        $item = $items[$data['item']];
        abort_if($item['unit'] === 'عدد' && floor((float) $data['quantity']) !== (float) $data['quantity'], 422, 'تعداد سکه باید عدد صحیح باشد.');

        $increase = InventoryIncreaseRequest::create([
            'user_id' => $user->id,
            'metal' => $item['metal'],
            'purity' => $item['purity'],
            'grams' => $data['quantity'],
            'note' => $data['note'] ?? null,
        ]);

        User::where('is_admin', true)->each(fn (User $admin) => Notification::create([
            'user_id' => $admin->id,
            'title' => "درخواست افزایش موجودی ربات — {$user->name}",
            'body' => "{$increase->grams} {$item['unit']} {$item['label']}",
            'type' => 'system',
        ]));

        return response()->json(['id' => $increase->id, 'label' => $item['label'], 'unit' => $item['unit']]);
    }

    public function overview(Request $request, PriceService $prices): JsonResponse
    {
        $this->authorize($request);
        $user = $this->vipUserForChat($request);

        return response()->json([
            'wallet_balance' => $user->walletBalance(),
            'assets' => [
                'gold' => $user->goldBalance(),
                'silver_999' => $user->silverBalance('999'),
                'silver_995' => $user->silverBalance('995'),
            ],
            'prices' => $prices->all(),
            'trades' => Transaction::query()->where('user_id', $user->id)->latest()->take(20)->get(['id', 'type', 'item_label', 'quantity', 'total', 'status', 'created_at']),
            'deposits' => DepositRequest::query()->where('user_id', $user->id)->latest()->take(20)->get(['id', 'amount', 'note', 'status', 'admin_note', 'created_at']),
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $this->authorize($request);
        $user = $this->vipUserForChat($request);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $deposit = DepositRequest::create([
            'user_id' => $user->id,
            'amount' => $data['amount'],
            'note' => $data['note'] ?? 'درخواست ثبت‌شده از ربات تلگرام',
            'status' => 'pending',
        ]);
        User::where('is_admin', true)->each(fn (User $admin) => Notification::create([
            'user_id' => $admin->id,
            'title' => "درخواست افزایش موجودی ربات — {$user->name}",
            'body' => number_format($deposit->amount).' تومان — در انتظار بررسی',
            'type' => 'wallet',
        ]));

        return response()->json(['id' => $deposit->id, 'status' => $deposit->status], 201);
    }

    public function trade(Request $request, PriceService $prices): JsonResponse
    {
        $this->authorize($request);
        $user = $this->vipUserForChat($request);
        $data = $request->validate([
            'side' => ['required', 'in:buy,sell'],
            'unit' => ['required', 'in:mesghal,gram'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $item = $data['unit'] === 'mesghal' ? 'mithqal' : 'geram';
        $label = $data['unit'] === 'mesghal' ? 'مثقال طلا' : 'گرم طلا';
        $quantity = (float) $data['quantity'];
        $grams = $data['unit'] === 'mesghal' ? round($quantity * (float) env('MITHQAL_GRAMS', 4.3318), 4) : $quantity;
        abort_if($grams < 10, 422, 'حداقل مقدار معامله ۱۰ گرم است.');
        $price = (float) data_get($prices->all(), "gold.{$item}");
        abort_unless($price > 0, 422, 'قیمت در دسترس نیست.');
        $total = (int) round($quantity * $price);

        $transaction = DB::transaction(function () use ($user, $data, $item, $label, $quantity, $grams, $price, $total) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($data['side'] === 'buy') {
                abort_if($user->walletBalance() < $total, 422, 'موجودی کیف پول کافی نیست.');
                WalletTransaction::create(['user_id' => $user->id, 'amount' => -$total, 'type' => 'withdraw', 'description' => "خرید {$label} از ربات"]);
                GoldLedger::create(['user_id' => $user->id, 'grams' => $grams, 'type' => 'purchase', 'description' => "خرید {$label} از ربات"]);
            } else {
                abort_if($user->goldBalance() < $grams, 422, 'موجودی طلای شما کافی نیست.');
                WalletTransaction::create(['user_id' => $user->id, 'amount' => $total, 'type' => 'deposit', 'description' => "فروش {$label} از ربات"]);
                GoldLedger::create(['user_id' => $user->id, 'grams' => -$grams, 'type' => 'sale', 'description' => "فروش {$label} از ربات"]);
            }

            return Transaction::create([
                'user_id' => $user->id,
                'type' => $data['side'],
                'item' => $item,
                'item_label' => $label,
                'quantity' => $quantity,
                'price_per_unit' => (int) $price,
                'total' => $total,
                'status' => 'active',
                'admin_note' => 'ثبت‌شده از ربات تلگرام',
            ]);
        });
        ActivityLog::record('telegram_trade', 'trade', "ثبت {$data['side']} {$label} از ربات", $user->id);

        return response()->json($transaction, 201);
    }

    public function receipt(Request $request): JsonResponse
    {
        $this->authorize($request);
        $user = $this->vipUserForChat($request);
        $data = $request->validate([
            'deposit_id' => ['required', 'integer'],
            'receipt' => ['required', 'image', 'max:5120'],
        ]);
        $deposit = DepositRequest::query()->where('user_id', $user->id)->where('status', 'pending')->findOrFail($data['deposit_id']);
        $deposit->update(['receipt_path' => $request->file('receipt')->store('receipts/telegram', 'public')]);

        return response()->json(['id' => $deposit->id, 'receipt_uploaded' => true]);
    }

    private function vipUserForChat(Request $request): User
    {
        $chatId = $request->validate(['telegram_chat_id' => ['required', 'string', 'max:32']])['telegram_chat_id'];
        $user = User::query()->where('telegram_chat_id', $chatId)->first();
        abort_unless($user && $user->isVipMember(), 403, 'عضویت ویژه فعال نیست.');

        return $user;
    }

    private function membershipPayload(User $user): array
    {
        return [
            'linked' => true,
            'vip' => true,
            'user_id' => $user->id,
            'name' => $user->name,
        ];
    }
}
