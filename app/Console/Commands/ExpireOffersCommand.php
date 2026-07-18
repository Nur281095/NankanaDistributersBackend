<?php

namespace App\Console\Commands;

use App\Enums\CatalogStatus;
use App\Models\Offer;
use Illuminate\Console\Command;

class ExpireOffersCommand extends Command
{
    protected $signature = 'offers:expire';

    protected $description = 'Mark active offers as inactive after their end date has passed';

    public function handle(): int
    {
        $expired = Offer::query()
            ->where('status', CatalogStatus::Active)
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => CatalogStatus::Inactive]);

        $this->info("Expired {$expired} offer(s).");

        return self::SUCCESS;
    }
}
