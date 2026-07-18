<?php

namespace App\Console\Commands;

use App\Services\LowStockAlertService;
use Illuminate\Console\Command;

class SweepLowStockAlertsCommand extends Command
{
    protected $signature = 'inventory:sweep-low-stock';

    protected $description = 'Notify admins about products that are currently at or below their low-stock threshold';

    public function handle(LowStockAlertService $lowStockAlertService): int
    {
        $alerted = $lowStockAlertService->sweep();

        $this->info("Low-stock sweep complete. Alerts queued for {$alerted} product(s).");

        return self::SUCCESS;
    }
}
