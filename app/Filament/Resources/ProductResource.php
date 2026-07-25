<?php

namespace App\Filament\Resources;

use App\Enums\CatalogStatus;
use App\Filament\Forms\Components\PublicImageUpload;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Services\DashboardService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Catalog placement')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('brand_id', null)),
                        Forms\Components\Select::make('brand_id')
                            ->relationship(
                                name: 'brand',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->when(
                                        filled($get('company_id')),
                                        fn (Builder $builder): Builder => $builder->where('company_id', $get('company_id')),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('company_id'))),
                    ]),
                Forms\Components\Section::make('Product details')
                    ->columns(2)
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
                        Forms\Components\TextInput::make('unit')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText('Lower numbers appear first in the list.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        PublicImageUpload::make('image')
                            ->label('Primary image')
                            ->directory('catalog/products')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Pricing')
                    ->description('Purchase price is admin-only and never exposed on the customer API.')
                    ->columns(3)
                    ->schema([
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
                    ]),
                Forms\Components\Section::make('Inventory & visibility')
                    ->columns(3)
                    ->schema([
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
                        Forms\Components\Toggle::make('is_taxable')
                            ->default(false),
                        Forms\Components\Toggle::make('is_featured')
                            ->default(false),
                        Forms\Components\Toggle::make('is_suggested')
                            ->default(false),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Product')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('sku_code')->label('SKU'),
                        Infolists\Components\TextEntry::make('sort_order'),
                        Infolists\Components\TextEntry::make('company.name')->label('Company'),
                        Infolists\Components\TextEntry::make('brand.name')->label('Brand'),
                        Infolists\Components\ImageEntry::make('image')
                            ->label('Primary image')
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('regular_price')->money('PKR'),
                        Infolists\Components\TextEntry::make('sale_price')->money('PKR')->placeholder('—'),
                        Infolists\Components\TextEntry::make('stock_quantity')->label('Stock'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->recordClasses(fn (Product $record): ?string => $record->isLowStock()
                ? 'bg-danger-50 dark:bg-danger-950/40'
                : null)
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
                Tables\Columns\TextColumn::make('brand.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale')
                    ->money('PKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('regular_price')
                    ->money('PKR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->money('PKR')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->color(fn (CatalogStatus $state): string => match ($state) {
                        CatalogStatus::Active => 'success',
                        CatalogStatus::Inactive => 'gray',
                    })
                    ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CatalogStatus::cases())->mapWithKeys(
                        fn (CatalogStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all()),
                Tables\Filters\TernaryFilter::make('is_featured'),
                Tables\Filters\TernaryFilter::make('is_suggested'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low stock')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\InventoryLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku_code'];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(DashboardService::class)->lowStockCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Products at or below low-stock threshold';
    }
}
