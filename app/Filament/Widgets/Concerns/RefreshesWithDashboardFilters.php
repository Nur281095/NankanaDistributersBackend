<?php

namespace App\Filament\Widgets\Concerns;

trait RefreshesWithDashboardFilters
{
    public function updatedFilters(): void
    {
        if (property_exists($this, 'cachedStats')) {
            $this->cachedStats = null;
        }

        if (property_exists($this, 'cachedData')) {
            $this->cachedData = null;
        }

        if (property_exists($this, 'dataChecksum') && method_exists($this, 'generateDataChecksum')) {
            $this->dataChecksum = $this->generateDataChecksum();
        }
    }
}
