<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkCode;
use App\Models\InventoryIncreaseRequest;
use App\Models\Notification;
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
