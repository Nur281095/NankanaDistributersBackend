<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Enums\CatalogStatus;
use App\Filament\Forms\ProductForm;
use App\Filament\Support\CatalogFormHelper;
use App\Filament\Support\LowStockRow;
use App\Models\Product;
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
        // Same fields as Product create/edit; company + brand come from the parent brand.
        return $form->schema(ProductForm::schema(includeCatalogPlacement: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->recordClasses(fn (Product $record): ?string => LowStockRow::classes($record))
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->visibility('public')
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku_code')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (Product $record): string => $record->isLowStock()
                        ? 'danger'
                        : 'success'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_suggested')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth('5xl')
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
                    ->modalWidth('5xl')
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
