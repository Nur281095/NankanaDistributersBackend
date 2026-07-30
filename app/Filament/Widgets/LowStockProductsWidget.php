<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\LowStockRow;
use App\Filament\Widgets\Concerns\RequiresActiveAdmin;
use App\Models\Product;
use App\Services\DashboardService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockProductsWidget extends BaseWidget
{
    use RequiresActiveAdmin;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock products')
            ->description('Products at or below their low-stock threshold.')
            ->paginated(false)
            ->query(
                fn (): Builder => Product::query()
                    ->lowStock()
                    ->orderBy('stock_quantity')
                    ->limit(10)
            )
            ->recordClasses(LowStockRow::CLASS_NAME)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('sku_code')
                    ->label('SKU'),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('low_stock_threshold')
                    ->label('Threshold'),
            ])
            ->emptyStateHeading('All products are above their low-stock threshold')
            ->emptyStateDescription('Inventory levels look healthy.');
    }
}
