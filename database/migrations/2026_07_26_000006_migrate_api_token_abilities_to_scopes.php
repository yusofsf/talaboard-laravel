<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TO_SCOPE = [
        'prices.read' => 'prices:read',
        'trade-room.read' => 'trades:read',
        'trade-room.create' => 'trades:create',
        'shop-order.create' => 'trades:create',
        'users.read' => 'profile:read',
    ];

    public function up(): void
    {
        DB::table('api_tokens')->orderBy('id')->each(function (object $token): void {
            $abilities = json_decode($token->abilities, true);
            if (! is_array($abilities)) {
                return;
            }

            $scopes = array_values(array_unique(array_map(
                fn (string $ability) => self::OLD_TO_SCOPE[$ability] ?? $ability,
                $abilities,
            )));

            if ($scopes !== $abilities) {
                DB::table('api_tokens')->where('id', $token->id)->update(['abilities' => json_encode($scopes)]);
            }
        });
    }

    public function down(): void
    {
        // Scope consolidation (two legacy create permissions into one) is lossy.
    }
};
