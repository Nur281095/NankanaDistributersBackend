<?php

namespace App\Filament\Resources\HomeSectionResource\RelationManagers;

use App\Enums\HomeSectionType;
use App\Filament\Forms\Components\HomeLinkFields;
use App\Filament\Forms\Components\PublicImageUpload;
use App\Models\HomeSection;
use App\Models\HomeSliderSlide;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class SlidesRelationManager extends RelationManager
{
    protected static string $relationship = 'slides';

    protected static ?string $title = 'Slides';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof HomeSection
            && $ownerRecord->type === HomeSectionType::Slider;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                PublicImageUpload::make('image')
                    ->directory('marketing/home/slides')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255),
                Forms\Components\TextInput::make('subtitle')
                    ->maxLength(500),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                ...HomeLinkFields::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->visibility('public')
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->placeholder('Untitled'),
                Tables\Columns\TextColumn::make('link_type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => str($state?->value ?? (string) $state)->headline()),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): HomeSliderSlide {
                        /** @var HomeSection $section */
                        $section = $this->getOwnerRecord();

                        $slider = $section->slider()->firstOrCreate([], [
                            'autoplay' => true,
                            'interval_ms' => 4000,
                        ]);

                        return $slider->slides()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function getRelationship(): Relation
    {
        /** @var HomeSection $section */
        $section = $this->getOwnerRecord();

        $slider = $section->slider()->firstOrCreate([], [
            'autoplay' => true,
            'interval_ms' => 4000,
        ]);

        return $slider->slides();
    }
}
