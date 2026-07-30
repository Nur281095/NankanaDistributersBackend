<?php

namespace App\Filament\Forms;

use App\Enums\CatalogStatus;
use App\Filament\Forms\Components\PublicImageUpload;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class ProductForm
{
    /**
     * Shared product fields for ProductResource and Brand → Products.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function schema(bool $includeCatalogPlacement = true): array
    {
        $sections = [];

        if ($includeCatalogPlacement) {
            $sections[] = Forms\Components\Section::make('Catalog placement')
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
                ]);
        }

        $sections[] = Forms\Components\Section::make('Product details')
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
            ]);

        $sections[] = Forms\Components\Section::make('Pricing')
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
            ]);

        $sections[] = Forms\Components\Section::make('Inventory & visibility')
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
                Forms\Components\Toggle::make('is_featured')
                    ->default(false),
                Forms\Components\Toggle::make('is_suggested')
                    ->default(false),
            ]);

        return $sections;
    }
}
