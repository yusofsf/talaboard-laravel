<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\GoldLedger;
use App\Models\Notification;
use App\Models\SilverLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CartController extends Controller
{
    private const MIN_GRAMS = 10.0;

    public function index(Request $request)
    {
        $items = CartItem::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CartItem $i) => $this->present($i));

        return Inertia::render('Cart', [
            'items' => $items,
            'totalBuy' => $items->where('trade_type', 'buy')->sum('total'),
            'totalSell' => $items->where('trade_type', 'sell')->sum('total'),
            'walletBalance' => $request->user()->walletBalance(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        CartItem::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return back()->with('success', 'آیتم از سبد خرید حذف شد.');
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        $items = CartItem::where('user_id', $user->id)->orderBy('id')->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'سبد خرید خالی است.');
        }

        $walletShortage = $this->walletShortage($user, $items);
        if ($walletShortage > 0) {
            return redirect()->route('wallet')->with('error', 'موجودی کیف پول کافی نیست. لطفاً کیف پول را حداقل به مبلغ ' . number_format($walletShortage) . ' تومان شارژ کنید و سپس سبد خرید را نهایی کنید.');
        }

        $error = $this->validateItems($user, $items);
        if ($error) {
            return back()->with('error', $error);
        }

        DB::transaction(function () use ($user, $items) {
            foreach ($items as $item) {
                $this->executeItem($user, $item);
            }

            CartItem::whereIn('id', $items->pluck('id'))->delete();
        });

        return redirect()->route('history')->with('success', 'سفارش‌های سبد خرید با موفقیت ثبت شد.');
    }

    private function validateItems(User $user, $items): ?string
    {
        $totalBuy = (int) $items->where('trade_type', 'buy')->sum('total');
        if ($totalBuy > 0 && $user->walletBalance() < $totalBuy) {
            return 'موجودی کیف پول شما برای ثبت سفارش‌های خرید کافی نیست.';
        }

        $goldNeeded = 0.0;
        $silverNeeded = ['999' => 0.0, '995' => 0.0];
        $coinNeeded = [];

        foreach ($items as $item) {
            if ($this->weightGrams($item) < self::MIN_GRAMS && $this->isWeightItem($item->item, $item->item_group)) {
                return "حداقل مقدار معامله برای «{$item->item_label}» ۱۰ گرم است.";
            }

            if ($item->trade_type !== 'sell') {
                continue;
            }

            if ($item->item === 'geram' || $item->item === 'mithqal') {
                $goldNeeded += $this->goldGrams($item->item, (float) $item->quantity);
            } elseif ($item->item_group === 'silver') {
                [$purity, $grams] = $this->silverGrams($item->item, (float) $item->quantity);
                $silverNeeded[$purity] += $grams;
            } else {
                $coinNeeded[$item->item] = ($coinNeeded[$item->item] ?? 0) + (float) $item->quantity;
            }
        }

        if ($goldNeeded > $user->goldBalance()) {
            return 'موجودی طلای شما برای سفارش‌های فروش کافی نیست.';
        }

        foreach ($silverNeeded as $purity => $grams) {
            if ($grams > $user->silverBalance($purity)) {
                return "موجودی نقره عیار {$purity} برای سفارش‌های فروش کافی نیست.";
            }
        }

        foreach ($coinNeeded as $item => $qty) {
            if ($this->coinHolding($user->id, $item) < $qty) {
                return 'موجودی سکه شما برای سفارش‌های فروش کافی نیست.';
            }
        }

        return null;
    }

    private function walletShortage(User $user, $items): int
    {
        $totalBuy = (int) $items->where('trade_type', 'buy')->sum('total');

        return max(0, $totalBuy - $user->walletBalance());
    }

    private function executeItem(User $user, CartItem $item): void
    {
        $typeLabel = $item->trade_type === 'buy' ? 'خرید' : 'فروش';

        Transaction::create([
            'user_id' => $user->id,
            'type' => $item->trade_type,
            'item' => $item->item,
            'item_label' => $item->item_label,
            'quantity' => $item->quantity,
            'price_per_unit' => $item->price_per_unit,
            'total' => $item->total,
        ]);

        WalletTransaction::create([
            'user_id' => $user->id,
            'amount' => $item->trade_type === 'buy' ? -$item->total : $item->total,
            'type' => $item->trade_type === 'buy' ? 'withdraw' : 'deposit',
            'description' => "{$typeLabel} {$item->item_label} ({$item->quantity}) از سبد خرید",
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => "ثبت {$typeLabel} — {$item->item_label}",
            'body' => "نوع: {$typeLabel} | مقدار: {$item->quantity} | مبلغ: " . number_format($item->total) . " تومان | تاریخ: " . Jalali::now(),
            'type' => 'trade',
        ]);

        if ($item->item === 'geram' || $item->item === 'mithqal') {
            $grams = $this->goldGrams($item->item, (float) $item->quantity);
            GoldLedger::create([
                'user_id' => $user->id,
                'grams' => $item->trade_type === 'buy' ? $grams : -$grams,
                'type' => $item->trade_type === 'buy' ? 'purchase' : 'sale',
                'description' => "{$typeLabel} از سبد خرید — {$item->item_label} ({$item->quantity})",
            ]);
        }

        if ($item->item_group === 'silver') {
            [$purity, $grams] = $this->silverGrams($item->item, (float) $item->quantity);
            SilverLedger::create([
                'user_id' => $user->id,
                'purity' => $purity,
                'grams' => $item->trade_type === 'buy' ? $grams : -$grams,
                'type' => $item->trade_type === 'buy' ? 'purchase' : 'sale',
                'description' => "{$typeLabel} از سبد خرید — {$item->item_label} ({$item->quantity})",
            ]);
        }

        ActivityLog::record('trade_' . $item->trade_type, 'trade',
            "{$typeLabel} {$item->item_label} از سبد خرید — مقدار: {$item->quantity} — مبلغ: " . number_format($item->total) . " تومان — کاربر: {$user->name}", $user->id);
    }

    private function present(CartItem $item): array
    {
        return [
            'id' => $item->id,
            'trade_type' => $item->trade_type,
            'item' => $item->item,
            'item_label' => $item->item_label,
            'quantity' => (float) $item->quantity,
            'price_per_unit' => $item->price_per_unit,
            'total' => $item->total,
            'created_at' => Jalali::format($item->created_at),
        ];
    }

    private function isWeightItem(string $item, string $group): bool
    {
        return $item === 'geram' || $item === 'mithqal' || $group === 'silver';
    }

    private function weightGrams(CartItem $item): float
    {
        if ($item->item_group === 'silver') {
            return $this->silverGrams($item->item, (float) $item->quantity)[1];
        }

        if ($item->item === 'geram' || $item->item === 'mithqal') {
            return $this->goldGrams($item->item, (float) $item->quantity);
        }

        return self::MIN_GRAMS;
    }

    private function coinHolding(int $userId, string $item): float
    {
        $base = Transaction::where('user_id', $userId)->where('item', $item)->where('status', 'active');
        return round((float) (clone $base)->where('type', 'buy')->sum('quantity') - (float) (clone $base)->where('type', 'sell')->sum('quantity'), 4);
    }

    private function silverGrams(string $item, float $qty): array
    {
        $purity = str_contains($item, '995') ? '995' : '999';
        $grams = str_starts_with($item, 'mithqal_') ? $qty * (float) env('MITHQAL_GRAMS', 4.3318) : $qty;
        return [$purity, round($grams, 4)];
    }

    private function goldGrams(string $item, float $qty): float
    {
        return round(($item === 'mithqal' ? $qty * (float) env('MITHQAL_GRAMS', 4.3318) : $qty), 4);
    }
}
