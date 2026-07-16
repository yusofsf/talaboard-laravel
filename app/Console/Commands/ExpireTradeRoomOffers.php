<?php

namespace App\Console\Commands;

use App\Services\TradeRoomExpiryService;
use Illuminate\Console\Command;

class ExpireTradeRoomOffers extends Command
{
    protected $signature = 'trade-room:expire-open-offers';

    protected $description = 'Cancel expired open trade-room offers and refund their escrow.';

    public function handle(TradeRoomExpiryService $expiry): int
    {
        $count = $expiry->expireOpenOffers();

        $this->info("Expired {$count} trade-room offer(s).");

        return self::SUCCESS;
    }
}
