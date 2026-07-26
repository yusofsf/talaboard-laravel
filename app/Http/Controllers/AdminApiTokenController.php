<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Article;
use App\Models\ArticleTag;
use App\Models\ArticleTopic;
use App\Models\CartItem;
use App\Models\TradeRoomOffer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminApiTokenController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/ApiTokens', [
            'tokens' => ApiToken::with('user')->latest()->get()->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'client_name' => $token->client_name,
                'user' => $token->user?->only(['id', 'name', 'phone']),
                'abilities' => $token->abilities,
                'last_used_at' => optional($token->last_used_at)->toDateTimeString(),
                'expires_at' => optional($token->expires_at)->toDateTimeString(),
                'created_at' => optional($token->created_at)->toDateTimeString(),
            ]),
            'users' => User::orderBy('name')->get(['id', 'name', 'phone']),
            'abilities' => ApiToken::ABILITIES,
            'userAbilities' => ApiToken::USER_ABILITIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_type' => 'required|in:user,guest',
            'user_id' => 'nullable|required_if:owner_type,user|exists:users,id',
            'client_name' => 'nullable|required_if:owner_type,guest|string|max:100',
            'name' => 'required|string|max:100',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'in:'.implode(',', array_keys(ApiToken::ABILITIES)),
            'expires_at' => 'nullable|date|after:now',
        ]);

        $user = $data['owner_type'] === 'user'
            ? User::findOrFail($data['user_id'])
            : null;

        if (! $user && array_intersect($data['abilities'], ApiToken::USER_ABILITIES)) {
            return back()->withErrors([
                'abilities' => 'قابلیت ثبت سفارش و مشاهده اطلاعات کاربر فقط برای توکن متصل به حساب کاربری قابل انتخاب است.',
            ])->withInput();
        }

        [, $plain] = ApiToken::issue(
            $user,
            $data['client_name'] ?? '',
            $data['name'],
            $data['abilities'],
            $data['expires_at'] ?? null,
        );

        return back()->with('issued_token', $plain)->with('success', 'توکن صادر شد؛ آن را همین حالا کپی کنید.');
    }

    public function destroy(int $id)
    {
        ApiToken::findOrFail($id)->delete();

        return back()->with('success', 'توکن لغو شد.');
    }

    private function models(): array
    {
        return [
            'users' => ['model' => User::class, 'label' => 'کاربران'],
            'articles' => ['model' => Article::class, 'label' => 'مقاله‌ها'],
            'article_tags' => ['model' => ArticleTag::class, 'label' => 'برچسب‌های مقاله'],
            'article_topics' => ['model' => ArticleTopic::class, 'label' => 'موضوعات مقاله'],
            'cart_items' => ['model' => CartItem::class, 'label' => 'اقلام سبد خرید'],
            'transactions' => ['model' => Transaction::class, 'label' => 'معاملات فروشگاه'],
            'trade_room_offers' => ['model' => TradeRoomOffer::class, 'label' => 'سفارش‌های اتاق معاملاتی'],
            'api_tokens' => ['model' => ApiToken::class, 'label' => 'توکن‌های API'],
        ];
    }

    public function recycleBin()
    {
        $models = $this->models();
        $items = collect($models)
            ->flatMap(fn ($config, $type) => $config['model']::onlyTrashed()
                ->latest('deleted_at')
                ->get()
                ->map(fn ($model) => [
                    'type' => $type,
                    'type_label' => $config['label'],
                    'id' => $model->id,
                    'label' => $model->name ?? $model->title ?? $model->item_label ?? ('رکورد شماره '.$model->id),
                    'deleted_at' => $model->deleted_at?->toDateTimeString(),
                ]))
            ->sortByDesc('deleted_at')
            ->values();

        return Inertia::render('Admin/RecycleBin', [
            'items' => $items,
            'types' => collect($models)->map(fn ($config, $type) => [
                'value' => $type,
                'label' => $config['label'],
            ])->values(),
        ]);
    }

    public function restore(string $type, int $id)
    {
        $models = $this->models();
        abort_unless(isset($models[$type]), 404);
        $models[$type]['model']::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'رکورد بازگردانی شد.');
    }
}
