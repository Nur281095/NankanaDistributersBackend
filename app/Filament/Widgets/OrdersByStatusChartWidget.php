<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Support\OrderPresentation;
use App\Filament\Widgets\Concerns\RefreshesWithDashboardFilters;
use App\Filament\Widgets\Concerns\RequiresActiveAdmin;
use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Str;

class OrdersByStatusChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use RefreshesWithDashboardFilters;
    use RequiresActiveAdmin;

    protected static ?string $heading = 'Orders by status';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = app(DashboardService::class)->ordersByStatus($this->filters);

        $labels = [];
        $data = [];
        $colors = [];

        foreach (OrderStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;

            if ($count === 0) {
                continue;
            }

            $labels[] = Str::headline($status->value);
            $data[] = $count;
            $colors[] = $this->colorForStatus($status);
        }

        if ($data === []) {
            $labels[] = 'No orders';
            $data[] = 0;
            $colors[] = '#d1d5db';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function colorForStatus(OrderStatus $status): string
    {
        return match (OrderPresentation::orderStatusColor($status)) {
            'info' => '#3b82f6',
            'warning' => '#f59e0b',
            'primary' => '#6366f1',
            'success' => '#22c55e',
            'danger' => '#ef4444',
            default => '#9ca3af',
        };
    }
}
