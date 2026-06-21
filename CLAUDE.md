# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A live gold/silver price board + trading platform for a jewelry/bullion shop (آبشده صفرپور), serving `metalsp.ir`. Laravel 13 (PHP 8.3) + Inertia.js + React 19, SQLite. This is a Laravel/React rewrite of an earlier Flask app (`talaboard-python`); the import command for migrating that app's data still exists (see below).

The whole UI is Persian/RTL (`dir="rtl"`, `APP_LOCALE=fa`). There is no English-language UI variant — don't add one.

## Commands

```bash
# Setup
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan storage:link   # required for membership docs/videos and delivery-request uploads to be servable

# Dev (server + queue + log tailer + vite, concurrently)
composer dev

# Build frontend only
npm run build        # production
npm run dev          # hot-reload

# Tests
composer test                       # = php artisan config:clear + php artisan test
php artisan test --filter=TradeControllerTest
php artisan test --filter=test_buy_gold_succeeds_and_debits_wallet

# Code style (Laravel Pint)
vendor/bin/pint
```

There is no JS linter/formatter configured (no ESLint/Prettier config present) — match existing style by hand.

### One-time data import from the old Flask app

```bash
php artisan import:flask-shop /path/to/shop.db   # or set FLASK_SHOP_DB_PATH in .env
```

Idempotent (upserts on `id`, preserves cross-table relations). **Imported users cannot log in with their old password** — Werkzeug hashed them with `scrypt`, which PHP has no compatible primitive for. Imported rows get `must_reset_password=true`; `AuthController::login()` detects this and force-redirects to the OTP password-reset flow instead of checking the password. Do not try to "fix" this by attempting scrypt verification in PHP — it was deliberately punted.

## Architecture

### Money/metal model — the load-bearing concept

Every user has **three balances**, each its own append-only ledger (never update/delete rows, only insert signed deltas and sum them):

| Balance | Table | Sum method |
|---|---|---|
| Cash (toman) | `wallet_transactions` | `User::walletBalance()` |
| Gold (grams) | `gold_ledger` | `User::goldBalance()` |
| Silver (grams, per purity 999/995) | `silver_ledger` | `User::silverBalance($purity)` |

Every feature that moves money or metal — shop trades, the P2P trade room, physical delivery requests, wallet settlement requests, admin manual adjustments — works by inserting rows into these three tables inside a `DB::transaction()`, never by mutating a stored balance column. When adding a new money/metal-moving feature, follow this pattern instead of inventing a new one.

**Escrow convention**: anything that reserves funds before a counterparty acts (trade-room offers, delivery requests, withdrawal requests) debits the ledger/wallet *immediately on creation*, then either completes the flow or refunds the exact amount back via a new ledger row tagged `*_refund` if rejected/cancelled. Never go back and edit the original entry.

### Gold/silver items are gram-based, with mesghal (مثقال) as an alternate unit on top

`TradeController::ITEMS` defines the tradeable catalogue: `mithqal`, `geram` (gold, gram-equivalent of `ABSHODE` stock), `bahar`/`nim`/`rob` (coins, piece-counted, no gram conversion), and `mithqal_999`/`gram_999`/`mithqal_995`/`gram_995` (silver). Mesghal quantities are converted to grams via `MITHQAL_GRAMS` (env, default 4.3318) before touching `gold_ledger`/`silver_ledger` — see `TradeController::goldGrams()` / `silverGrams()`. Coins are **not** tracked in a gram ledger; their "do you own enough to sell" check sums `transactions` directly (`coinHolding()`).

If you add another mesghal/gram pair for either metal, route it through the same conversion helpers rather than adding ad-hoc math.

### Price pipeline (`app/Services/PriceService.php`)

`PriceService::all()` is the single source of truth for current prices, called fresh on every request that needs it (no model caching beyond per-source `Cache::remember($key, CACHE_TTL)` inside each fetch method). It returns:

```
['gold' => [...], 'gold_buy' => [...], 'silver' => [...], 'silver_buy' => [...],
 'dollar' => [...], 'ounce' => ['gold' => ..., 'silver' => ...],
 'open' => [...], 'errors' => [...], 'updated_at' => ...]
```

- **Gold**: fetched from Talaland's `getAllPrices` API (`TALALAND_API_BASE`/`USERNAME`/`TOKEN`). `askPrice` is the sell-side mid, `bidPrice` the buy-side mid — these are genuinely different API fields, not the same number with a sign flip. `sell = ask * (1 + GOLD_FACTOR)`, `buy = bid * (1 - GOLD_FACTOR)`. Don't synthesize buy from sell or vice versa.
- **Silver**: read live from a separate SQLite database (`SILVER_DB_PATH`, connection name `silver` in `config/database.php`) that belongs to a *different* project (`sachmebot_laravel`, a Telegram price bot) — not generated by this app. Sell/buy come from that DB's own `_buy` columns directly, no factor applied.
- **Gold ounce (USD)**: alanchand.com scrape tried first, Yahoo Finance (`GC=F`) as fallback — deliberately in that order, because Yahoo is frequently unreachable from Iran and trying it first just burns the request timeout for nothing.
- **`open` prices**: the first non-null price seen each Jalali day, cached until end-of-day, used to compute the up/down % shown on the home page. This is a daily baseline snapshot, not a true previous-close.

### Dates are Jalali (Shamsi) everywhere, with two independent implementations

- **PHP**: `App\Helpers\Jalali` (no package dependency — hand-rolled conversion). `Jalali::format($carbonOrString, $withTime = true)` and `Jalali::now()`.
- **JS**: `resources/js/jalali.js`. Conversion uses `jalCal()` (Nowruz-date algorithm) for the Jalali side, but deliberately uses **native `Date.UTC` epoch-day arithmetic** for the Gregorian side rather than the classical `g2d`/`d2j` Julian-day formulas — those were found to silently misdate Jan/Feb-adjacent dates and pre-1980s years during development. If you touch this file, re-verify against known fixed points (Nowruz dates, 22 Bahman 1357 = 1979-02-11) before trusting it; round-trip-testing only against recent dates will not catch the bug class that bit this once already.

Both Jalali implementations independently convert/display dates; there is no shared date format passed between PHP and JS — the PHP side renders Jalali strings server-side for display, the JS side (`JalaliDatePicker.jsx`) is only used for date *input* (birth date), converting back to a Gregorian `YYYY-MM-DD` string before it ever reaches the backend.

### Inertia shared state (`HandleInertiaRequests`)

`auth.user` is shared on every request and carries `is_vip`, `is_admin`, `membership_level`, `wallet_balance`, `unread_count` precomputed — pages read these from `usePage().props` rather than fetching separately. `is_admin` is `$user->is_admin OR phone === ADMIN_PHONE` (env-based admin bootstrapping — first login from that phone auto-promotes, see `AuthController::login()`).

`is_vip` (boolean) and `membership_level` (1 = regular, 2 = VIP) are kept in sync everywhere they're set (`AdminController::setLevel()`, membership approval, invite-code redemption) — both checked together in most VIP gates (`User::isVipMember()`), so don't update one without the other.

### VIP membership: two independent paths to the same result

1. **Invite code** (`InviteCode` model) — instant, no review. Mostly legacy.
2. **Identity verification** (`MembershipController::apply()`) — national ID photo + business-license photo + a short self-recorded declaration video + birth date + address, reviewed by an admin (`AdminController::membershipApprove/Reject`). Client-side size caps (200KB images, 5MB video) are enforced in `Membership.jsx` *before* upload starts, in addition to server-side `max:` validation — don't remove the client-side check on the theory that server validation is enough, the point is avoiding a slow upload that fails at the end.

Video is recorded in-browser via `VideoRecorder.jsx` (`getUserMedia` + `MediaRecorder`, capped at 640×480/600kbps to keep file size down), not picked from disk — there's no plain `<input type=file>` fallback for video.

### Trade room (`TradeRoomController`) vs. shop trades (`TradeController`)

Two unrelated trading mechanisms:
- **Shop trades** (`/trade/{item}`): user buys from / sells to the shop itself, at `PriceService` prices. Requires VIP for nothing — open to all logged-in users.
- **Trade room** (`/trade-room`): VIP-only P2P order board. One VIP posts a buy/sell offer (escrowing wallet or metal on creation per the convention above), another VIP accepts it, ledgers settle directly between the two users — the shop is not a counterparty. `TradeRoomOffer.metal` distinguishes gold (no purity) from silver (purity 999/995); gold offers store `purity = ''` rather than `null` to dodge a `doctrine/dbal` dependency that would otherwise be needed to alter the column nullable.

### Physical delivery & cash-out requests live on the Inventory/Wallet pages, not standalone pages

`SilverDeliveryController` (handles gold *and* silver despite the class name — kept for historical reasons) and the withdrawal-request flow in `WalletController` are both embedded directly into `Inventory.jsx` and `Wallet.jsx` respectively as inline forms + history tables, not separate routed pages. If you're tempted to give them their own page, that was explicitly undone once already per a prior request ("تو موجودی انبار باشه" — keep it in inventory).

### Deployment topology (cPanel) — only relevant if asked about production issues

Production's `public_html` (the actual web server document root) is a **separate directory** from this Laravel app's `public/`, connected by hand-made symlinks for `index.php`, `public/build`, and `public/storage` — not a single docroot pointer. Any new top-level static file added to `public/` (the way `public/logo.jpg` was) needs its own manual symlink in `public_html` on the server or it 404s through Laravel's catch-all route, even though the file exists and is correctly referenced in code. This has been the recurring cause of "X isn't showing up in production" reports — check for a missing symlink before assuming a code bug.

## Key environment variables

| Var | Purpose |
|---|---|
| `ADMIN_PHONE` | Phone number that auto-promotes to admin on first login |
| `MASTER_OTP` | Universal OTP fallback (default `000000`) for when SMS delivery fails — blank disables it. This is an intentional backdoor for ops, not a bug. |
| `GOLD_FACTOR` | Buy/sell spread fraction around the gold mid-price (e.g. `0.01` = 1%) |
| `TALALAND_API_BASE`/`USERNAME`/`TOKEN` | Gold price source |
| `SILVER_DB_PATH` | Path to the *other* project's (sachmebot_laravel) SQLite DB — read-only |
| `FLASK_SHOP_DB_PATH` | Old Flask app's `shop.db`, only for the one-time import command |
| `MITHQAL_GRAMS` | Mesghal→gram conversion factor, default 4.3318 |
| `CACHE_TTL` | Per-price-source cache duration (seconds) |
| `REFRESH_SECONDS` | Client-side polling interval for the home page price board |
