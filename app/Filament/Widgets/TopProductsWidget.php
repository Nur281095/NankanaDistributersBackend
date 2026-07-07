<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RefreshesWithDashboardFilters;
use App\Filament\Widgets\Concerns\RequiresActiveAdmin;
use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TopProductsWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use RefreshesWithDashboardFilters;
    use RequiresActiveAdmin;

    protected static ?string $heading = 'Top products';

    protected static ?string $description = 'Best sellers by quantity in the selected date range.';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $products = app(DashboardService::class)->topProducts($this->filters);

        if ($products->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Quantity sold',
                        'data' => [0],
                        'backgroundColor' => '#d1d5db',
                    ],
                ],
                'labels' => ['No sales in range'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Quantity sold',
                    'data' => $products->pluck('total_quantity')->map(fn ($value): int => (int) $value)->all(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $products->pluck('product_name')->all(),
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
