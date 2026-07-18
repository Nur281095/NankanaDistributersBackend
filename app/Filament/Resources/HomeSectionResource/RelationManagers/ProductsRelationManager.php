<?php

namespace App\Filament\Resources\HomeSectionResource\RelationManagers;

use App\Enums\CatalogStatus;
use App\Enums\HomeSectionType;
use App\Enums\ProductCollectionSource;
use App\Models\HomeSection;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Manual products';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof HomeSection
            && $ownerRecord->type === HomeSectionType::ProductCollection
            && $ownerRecord->product_source === ProductCollectionSource::Manual;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('home_section_products.sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku_code')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('regular_price')
                    ->money('PKR'),
                Tables\Columns\IconColumn::make('status')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (Product $record): bool => $record->status === CatalogStatus::Active),
                Tables\Columns\TextColumn::make('pivot.sort_order')
                    ->label('#')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->active()->orderBy('name'))
                    ->recordSelectSearchColumns(['name', 'sku_code'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
