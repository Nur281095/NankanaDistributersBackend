<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RefreshesWithDashboardFilters;
use App\Filament\Widgets\Concerns\RequiresActiveAdmin;
use App\Services\DashboardService;
use App\Services\SettingsService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use RefreshesWithDashboardFilters;
    use RequiresActiveAdmin;

    protected static ?string $heading = 'Sales trend';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(DashboardService::class)->salesTrend($this->filters);
        $currency = app(SettingsService::class)->currency();

        return [
            'datasets' => [
                [
                    'label' => "Revenue ({$currency})",
                    'data' => $trend['revenue'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Orders',
                    'data' => $trend['orders'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.08)',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'position' => 'left',
                ],
                'y1' => [
                    'beginAtZero' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
