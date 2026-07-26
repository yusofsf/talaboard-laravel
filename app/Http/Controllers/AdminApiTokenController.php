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
        return Inertia::render('Admin/ApiTokens', ['tokens' => ApiToken::with('user')->latest()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'user' => $t->user?->only(['id', 'name', 'phone']), 'abilities' => $t->abilities, 'last_used_at' => optional($t->last_used_at)->toDateTimeString(), 'deleted_at' => optional($t->deleted_at)->toDateTimeString()]), 'users' => User::orderBy('name')->get(['id', 'name', 'phone']), 'abilities' => ApiToken::ABILITIES]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['user_id' => 'required|exists:users,id', 'name' => 'required|string|max:100', 'abilities' => 'required|array|min:1', 'abilities.*' => 'in:'.implode(',', array_keys(ApiToken::ABILITIES)), 'expires_at' => 'nullable|date|after:now']);
        [, $plain] = ApiToken::issue(User::findOrFail($d['user_id']), $d['name'], $d['abilities'], $d['expires_at'] ?? null);

        return back()->with('issued_token', $plain)->with('success', 'توکن صادر شد؛ آن را همین حالا کپی کنید.');
    }

    public function destroy(int $id)
    {
        ApiToken::findOrFail($id)->delete();

        return back()->with('success', 'توکن لغو شد.');
    }

    private function models(): array
    {
        return ['users' => User::class, 'articles' => Article::class, 'article_tags' => ArticleTag::class, 'article_topics' => ArticleTopic::class, 'cart_items' => CartItem::class, 'transactions' => Transaction::class, 'trade_room_offers' => TradeRoomOffer::class, 'api_tokens' => ApiToken::class];
    }

    public function recycleBin()
    {
        $items = collect($this->models())->flatMap(fn ($class, $type) => $class::onlyTrashed()->latest('deleted_at')->get()->map(fn ($m) => ['type' => $type, 'id' => $m->id, 'label' => $m->name ?? $m->title ?? $m->item_label ?? ('#'.$m->id), 'deleted_at' => $m->deleted_at?->toDateTimeString()]))->sortByDesc('deleted_at')->values();

        return Inertia::render('Admin/RecycleBin', ['items' => $items]);
    }

    public function restore(string $type, int $id)
    {
        $models = $this->models();
        abort_unless(isset($models[$type]), 404);
        $models[$type]::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success','رکورد بازگردانی شد.');
    }
}
