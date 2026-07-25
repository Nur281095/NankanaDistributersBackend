<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Enums\CatalogStatus;
use App\Filament\Support\CatalogFormHelper;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sku_code')
                    ->label('SKU')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->helperText('SKU can be reused across products.'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->helperText('Lower numbers appear first in the list.'),
                Forms\Components\TextInput::make('regular_price')
                    ->numeric()
                    ->required()
                    ->prefix('PKR')
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\TextInput::make('sale_price')
                    ->numeric()
                    ->prefix('PKR')
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\TextInput::make('purchase_price')
                    ->numeric()
                    ->prefix('PKR')
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\TextInput::make('stock_quantity')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('low_stock_threshold')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(10),
                Forms\Components\Select::make('status')
                    ->enum(CatalogStatus::class)
                    ->options(collect(CatalogStatus::cases())->mapWithKeys(
                        fn (CatalogStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all())
                    ->required()
                    ->default(CatalogStatus::Active),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->recordClasses(fn (Product $record): ?string => $record->isLowStock()
                ? 'bg-danger-50 dark:bg-danger-950/40'
                : null)
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku_code')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (Product $record): string => $record->isLowStock()
                        ? 'danger'
                        : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();
                        $data['company_id'] = $owner->company_id;
                        $data['slug'] = CatalogFormHelper::uniqueSlug(
                            $data['name'],
                            'products',
                        );

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, Product $record): array {
                        $data['slug'] = filled($record->slug)
                            ? (string) $record->slug
                            : CatalogFormHelper::uniqueSlug($data['name'], 'products', $record->id);

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]));
    }
}
