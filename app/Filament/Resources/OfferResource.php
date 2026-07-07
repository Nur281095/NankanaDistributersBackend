<?php

namespace App\Filament\Resources;

use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Filament\Resources\OfferResource\Pages;
use App\Filament\Resources\OfferResource\RelationManagers;
use App\Models\Offer;
use App\Services\OfferService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Offer details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->enum(CatalogStatus::class)
                            ->options(collect(CatalogStatus::cases())->mapWithKeys(
                                fn (CatalogStatus $status): array => [$status->value => Str::headline($status->value)]
                            )->all())
                            ->required()
                            ->default(CatalogStatus::Active)
                            ->native(false),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('image')
                            ->image()
                            ->directory('marketing/offers')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Discount')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->enum(DiscountType::class)
                            ->options(collect(DiscountType::cases())->mapWithKeys(
                                fn (DiscountType $type): array => [$type->value => Str::headline($type->value)]
                            )->all())
                            ->required()
                            ->live()
                            ->native(false),
                        Forms\Components\TextInput::make('discount_value')
                            ->label(fn (Get $get): string => $get('discount_type') === DiscountType::Percentage->value
                                ? 'Discount percentage'
                                : 'Discount amount (PKR)')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): ?float => $get('discount_type') === DiscountType::Percentage->value ? 100 : null)
                            ->required(),
                    ]),
                Forms\Components\Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('end_date')
                            ->required()
                            ->afterOrEqual('start_date')
                            ->native(false),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Offer details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (CatalogStatus $state): string => match ($state) {
                                CatalogStatus::Active => 'success',
                                CatalogStatus::Inactive => 'gray',
                            })
                            ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
                        Infolists\Components\ImageEntry::make('image')
                            ->disk('public')
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Discount & schedule')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('discount_type')
                            ->formatStateUsing(fn (DiscountType $state): string => Str::headline($state->value)),
                        Infolists\Components\TextEntry::make('discount_value')
                            ->formatStateUsing(function (Offer $record, $state): string {
                                if ($record->discount_type === DiscountType::Percentage) {
                                    return number_format((float) $state, 2).'%';
                                }

                                return 'PKR '.number_format((float) $state, 2);
                            }),
                        Infolists\Components\TextEntry::make('start_date')
                            ->date(),
                        Infolists\Components\TextEntry::make('end_date')
                            ->date(),
                        Infolists\Components\IconEntry::make('is_currently_active')
                            ->label('Currently active')
                            ->boolean()
                            ->state(fn (Offer $record): bool => app(OfferService::class)->isCurrentlyActive($record)),
                        Infolists\Components\TextEntry::make('targets_count')
                            ->label('Targets')
                            ->state(fn (Offer $record): int => $record->targets()->count()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->badge()
                    ->formatStateUsing(fn (DiscountType $state): string => Str::headline($state->value))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(function (Offer $record, $state): string {
                        if ($record->discount_type === DiscountType::Percentage) {
                            return number_format((float) $state, 0).'%';
                        }

                        return 'PKR '.number_format((float) $state, 0);
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (CatalogStatus $state): string => match ($state) {
                        CatalogStatus::Active => 'success',
                        CatalogStatus::Inactive => 'gray',
                    })
                    ->formatStateUsing(fn (CatalogStatus $state): string => Str::headline($state->value)),
                Tables\Columns\IconColumn::make('is_currently_active')
                    ->label('Live now')
                    ->boolean()
                    ->state(fn (Offer $record): bool => app(OfferService::class)->isCurrentlyActive($record)),
                Tables\Columns\TextColumn::make('targets_count')
                    ->counts('targets')
                    ->label('Targets'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CatalogStatus::cases())->mapWithKeys(
                        fn (CatalogStatus $status): array => [$status->value => Str::headline($status->value)]
                    )->all()),
                Tables\Filters\Filter::make('currently_active')
                    ->label('Live now')
                    ->query(function (Builder $query): Builder {
                        $today = now()->toDateString();

                        return $query
                            ->where('status', CatalogStatus::Active)
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today);
                    }),
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
            RelationManagers\TargetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'view' => Pages\ViewOffer::route('/{record}'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
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
        return ['title', 'description'];
    }
}
