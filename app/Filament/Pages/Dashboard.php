<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LowStockProductsWidget;
use App\Filament\Widgets\OrdersByStatusChartWidget;
use App\Filament\Widgets\SalesChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopProductsWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Dashboard';

    public function mount(): void
    {
        if ($this->filters === null) {
            $this->filters = [
                'start_date' => now()->subDays(29)->toDateString(),
                'end_date' => now()->toDateString(),
            ];
        }

        $this->mountHasFilters();
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Date range')
                    ->description('Filter dashboard metrics and charts.')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('From')
                            ->required()
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label('To')
                            ->required()
                            ->afterOrEqual('start_date')
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            SalesChartWidget::class,
            OrdersByStatusChartWidget::class,
            TopProductsWidget::class,
            LowStockProductsWidget::class,
        ];
    }

    /**
     * @return int|string|array<string, int|string|null>
     */
    public function getColumns(): int|string|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
