<?php

namespace App\Filament\Forms\Components;

use App\Enums\CatalogStatus;
use App\Enums\HomeLinkType;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Offer;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;

final class HomeLinkFields
{
    /**
     * @return list<Forms\Components\Component>
     */
    public static function make(string $typeField = 'link_type', string $valueField = 'link_value'): array
    {
        return [
            Forms\Components\Select::make($typeField)
                ->label('Link type')
                ->enum(HomeLinkType::class)
                ->options(collect(HomeLinkType::cases())->mapWithKeys(
                    fn (HomeLinkType $type): array => [$type->value => Str::headline($type->value)]
                )->all())
                ->default(HomeLinkType::None->value)
                ->required()
                ->live()
                ->native(false)
                ->afterStateUpdated(fn (Set $set) => $set($valueField, null)),
            Forms\Components\Select::make($valueField)
                ->label(fn (Get $get): string => match ($get($typeField)) {
                    HomeLinkType::Product->value => 'Product',
                    HomeLinkType::Brand->value => 'Brand',
                    HomeLinkType::Company->value => 'Company',
                    HomeLinkType::Offer->value => 'Offer',
                    default => 'Target',
                })
                ->searchable()
                ->preload()
                ->options(fn (Get $get): array => match ($get($typeField)) {
                    HomeLinkType::Product->value => Product::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
                    HomeLinkType::Brand->value => Brand::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
                    HomeLinkType::Company->value => Company::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
                    HomeLinkType::Offer->value => Offer::query()
                        ->where('status', CatalogStatus::Active)
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all(),
                    default => [],
                })
                ->required()
                ->visible(fn (Get $get): bool => in_array($get($typeField), [
                    HomeLinkType::Product->value,
                    HomeLinkType::Brand->value,
                    HomeLinkType::Company->value,
                    HomeLinkType::Offer->value,
                ], true))
                ->dehydrated(fn (Get $get): bool => in_array($get($typeField), [
                    HomeLinkType::Product->value,
                    HomeLinkType::Brand->value,
                    HomeLinkType::Company->value,
                    HomeLinkType::Offer->value,
                ], true)),
            Forms\Components\TextInput::make($valueField)
                ->label('URL')
                ->url()
                ->maxLength(500)
                ->required()
                ->visible(fn (Get $get): bool => $get($typeField) === HomeLinkType::Url->value)
                ->dehydrated(fn (Get $get): bool => $get($typeField) === HomeLinkType::Url->value),
        ];
    }
}
