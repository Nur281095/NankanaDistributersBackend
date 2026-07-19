<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Enums\CatalogStatus;
use App\Filament\Forms\Components\PublicImageUpload;
use App\Filament\Support\CatalogFormHelper;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class BrandsRelationManager extends RelationManager
{
    protected static string $relationship = 'brands';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
                        if (filled($get('slug'))) {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash(),
                PublicImageUpload::make('logo')
                    ->directory('catalog/brands'),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->enum(CatalogStatus::class)
                    ->options(collect(CatalogStatus::cases())->mapWithKeys(
                        fn (CatalogStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all())
                    ->required()
                    ->default(CatalogStatus::Active),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->disk('public')
                    ->visibility('public')
                    ->checkFileExistence(false)
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['slug'] = CatalogFormHelper::uniqueSlug(
                            $data['slug'] ?? $data['name'],
                            'brands',
                        );

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, Brand $record): array {
                        $data['slug'] = CatalogFormHelper::uniqueSlug(
                            $data['slug'] ?? $data['name'],
                            'brands',
                            $record->id,
                        );

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
