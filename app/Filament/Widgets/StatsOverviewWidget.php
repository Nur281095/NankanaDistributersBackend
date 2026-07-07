<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Widgets\Concerns\RefreshesWithDashboardFilters;
use App\Filament\Widgets\Concerns\RequiresActiveAdmin;
use App\Services\DashboardService;
use App\Services\SettingsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use RefreshesWithDashboardFilters;
    use RequiresActiveAdmin;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = app(DashboardService::class)->stats($this->filters);
        $currency = app(SettingsService::class)->currency();

        return [
            Stat::make('Orders', number_format($stats['orders_count']))
                ->description('Placed in selected range')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Revenue', $currency.' '.number_format((float) $stats['revenue_total'], 2))
                ->description('Excludes cancelled orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Active orders', number_format($stats['active_orders_count']))
                ->description('Received, packed, or on the way')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning')
                ->url(OrderResource::getUrl('index')),
            Stat::make('COD pending', number_format($stats['cod_pending_count']))
                ->description('Awaiting cash collection')
                ->descriptionIcon('heroicon-m-currency-rupee')
                ->color('info'),
            Stat::make('Low stock', number_format($stats['low_stock_count']))
                ->description('Products at or below threshold')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats['low_stock_count'] > 0 ? 'danger' : 'gray')
                ->url(ProductResource::getUrl('index')),
            Stat::make('New customers', number_format($stats['new_customers_count']))
                ->description('Registered in selected range')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('gray'),
        ];
    }
}
