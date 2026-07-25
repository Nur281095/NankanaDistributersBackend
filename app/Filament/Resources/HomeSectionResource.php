<?php

namespace App\Filament\Resources;

use App\Enums\HomeLinkType;
use App\Enums\HomeSectionType;
use App\Enums\ProductCollectionSource;
use App\Filament\Forms\Components\HomeLinkFields;
use App\Filament\Forms\Components\PublicImageUpload;
use App\Filament\Resources\HomeSectionResource\Pages;
use App\Filament\Resources\HomeSectionResource\RelationManagers;
use App\Models\HomeSection;
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

class HomeSectionResource extends Resource
{
    protected static ?string $model = HomeSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Home sections';

    protected static ?string $modelLabel = 'Home section';

    protected static ?string $pluralModelLabel = 'Home sections';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Section')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->enum(HomeSectionType::class)
                            ->options(collect(HomeSectionType::cases())->mapWithKeys(
                                fn (HomeSectionType $type): array => [$type->value => Str::headline($type->value)]
                            )->all())
                            ->required()
                            ->live()
                            ->native(false)
                            ->disabled(fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated()
                            ->helperText('Type cannot be changed after create. Create a new section instead.'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('type') === HomeSectionType::ProductCollection->value),
                        Forms\Components\TextInput::make('subtitle')
                            ->maxLength(500),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText('Or drag rows on the list page to reorder.'),
                    ]),
                Forms\Components\Section::make('Product collection')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => $get('type') === HomeSectionType::ProductCollection->value)
                    ->schema([
                        Forms\Components\Select::make('product_source')
                            ->enum(ProductCollectionSource::class)
                            ->options(collect(ProductCollectionSource::cases())->mapWithKeys(
                                fn (ProductCollectionSource $source): array => [$source->value => Str::headline($source->value)]
                            )->all())
                            ->required(fn (Get $get): bool => $get('type') === HomeSectionType::ProductCollection->value)
                            ->live()
                            ->native(false)
                            ->helperText('Manual collections are curated on the Products tab after saving.'),
                        Forms\Components\TextInput::make('product_limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10)
                            ->required(fn (Get $get): bool => $get('type') === HomeSectionType::ProductCollection->value)
                            ->helperText('Max products returned by the home API for this section (1–50).'),
                    ]),
                Forms\Components\Section::make('Slider settings')
                    ->relationship('slider')
                    ->visible(fn (Get $get): bool => $get('type') === HomeSectionType::Slider->value)
                    ->schema([
                        Forms\Components\Toggle::make('autoplay')
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('interval_ms')
                            ->label('Autoplay interval (ms)')
                            ->numeric()
                            ->minValue(1000)
                            ->maxValue(60000)
                            ->default(4000)
                            ->required()
                            ->helperText('Used when autoplay is enabled. Manage slides on the Slides tab after saving.'),
                    ]),
                Forms\Components\Section::make('Banner')
                    ->relationship('banner')
                    ->visible(fn (Get $get): bool => $get('type') === HomeSectionType::Banner->value)
                    ->schema([
                        PublicImageUpload::make('image')
                            ->directory('marketing/home/banners')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Banner active')
                            ->default(true)
                            ->required(),
                        ...HomeLinkFields::make(),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Section')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (HomeSectionType $state): string => Str::headline($state->value)),
                        Infolists\Components\IconEntry::make('is_active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('title')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('subtitle')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('sort_order'),
                        Infolists\Components\TextEntry::make('product_source')
                            ->formatStateUsing(fn (?ProductCollectionSource $state): string => $state
                                ? Str::headline($state->value)
                                : '—')
                            ->visible(fn (HomeSection $record): bool => $record->type === HomeSectionType::ProductCollection),
                        Infolists\Components\TextEntry::make('product_limit')
                            ->visible(fn (HomeSection $record): bool => $record->type === HomeSectionType::ProductCollection),
                    ]),
                Infolists\Components\Section::make('Slider')
                    ->columns(2)
                    ->visible(fn (HomeSection $record): bool => $record->type === HomeSectionType::Slider)
                    ->schema([
                        Infolists\Components\IconEntry::make('slider.autoplay')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('slider.interval_ms')
                            ->label('Interval (ms)'),
                        Infolists\Components\TextEntry::make('slider.slides_count')
                            ->label('Slides')
                            ->state(fn (HomeSection $record): int => $record->slider?->slides()->count() ?? 0),
                    ]),
                Infolists\Components\Section::make('Banner')
                    ->columns(2)
                    ->visible(fn (HomeSection $record): bool => $record->type === HomeSectionType::Banner)
                    ->schema([
                        Infolists\Components\ImageEntry::make('banner.image')
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('banner.title')
                            ->placeholder('—'),
                        Infolists\Components\IconEntry::make('banner.is_active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('banner.link_type')
                            ->formatStateUsing(fn (?HomeLinkType $state): string => $state
                                ? Str::headline($state->value)
                                : '—'),
                        Infolists\Components\TextEntry::make('banner.link_value')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(
                fn (Tables\Actions\Action $action, bool $isReordering): Tables\Actions\Action => $action
                    ->button()
                    ->label($isReordering ? 'Done reordering' : 'Reorder sections')
                    ->icon($isReordering ? 'heroicon-m-check' : 'heroicon-m-arrows-up-down')
                    ->color($isReordering ? 'success' : 'gray')
                    ->tooltip($isReordering
                        ? 'Save order and return to normal view'
                        : 'Show drag handles on each row, then drag to change home feed order'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Untitled'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (HomeSectionType $state): string => Str::headline($state->value))
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_source')
                    ->label('Source')
                    ->formatStateUsing(fn (?ProductCollectionSource $state): string => $state
                        ? Str::headline($state->value)
                        : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product_limit')
                    ->label('Limit')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(HomeSectionType::cases())->mapWithKeys(
                        fn (HomeSectionType $type): array => [$type->value => Str::headline($type->value)]
                    )->all()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('moveUp')
                    ->label('Up')
                    ->icon('heroicon-m-arrow-up')
                    ->color('gray')
                    ->action(fn (HomeSection $record) => static::swapSortOrder($record, direction: -1))
                    ->visible(fn (HomeSection $record): bool => static::neighborSortOrder($record, direction: -1) !== null),
                Tables\Actions\Action::make('moveDown')
                    ->label('Down')
                    ->icon('heroicon-m-arrow-down')
                    ->color('gray')
                    ->action(fn (HomeSection $record) => static::swapSortOrder($record, direction: 1))
                    ->visible(fn (HomeSection $record): bool => static::neighborSortOrder($record, direction: 1) !== null),
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

    private static function neighborSortOrder(HomeSection $record, int $direction): ?HomeSection
    {
        $query = HomeSection::query()->whereNull('deleted_at');

        if ($direction < 0) {
            return $query
                ->where('sort_order', '<', $record->sort_order)
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->first();
        }

        return $query
            ->where('sort_order', '>', $record->sort_order)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    private static function swapSortOrder(HomeSection $record, int $direction): void
    {
        $neighbor = static::neighborSortOrder($record, $direction);

        if ($neighbor === null) {
            return;
        }

        $currentOrder = $record->sort_order;
        $record->update(['sort_order' => $neighbor->sort_order]);
        $neighbor->update(['sort_order' => $currentOrder]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SlidesRelationManager::class,
            RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeSections::route('/'),
            'create' => Pages\CreateHomeSection::route('/create'),
            'view' => Pages\ViewHomeSection::route('/{record}'),
            'edit' => Pages\EditHomeSection::route('/{record}/edit'),
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
        return ['title', 'subtitle'];
    }
}
