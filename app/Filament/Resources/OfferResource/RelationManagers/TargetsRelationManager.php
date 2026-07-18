<?php

namespace App\Filament\Resources\OfferResource\RelationManagers;

use App\Enums\OfferTargetType;
use App\Filament\Support\OfferFormHelper;
use App\Models\OfferTarget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class TargetsRelationManager extends RelationManager
{
    protected static string $relationship = 'targets';

    protected static ?string $title = 'Offer targets';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('target_type')
                    ->label('Target type')
                    ->enum(OfferTargetType::class)
                    ->options(collect(OfferTargetType::cases())->mapWithKeys(
                        fn (OfferTargetType $type): array => [$type->value => Str::headline($type->value)]
                    )->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('target_id', null))
                    ->native(false),
                Forms\Components\Select::make('target_id')
                    ->label('Target')
                    ->options(fn (Get $get): array => match (OfferTargetType::tryFrom((string) $get('target_type'))) {
                        OfferTargetType::Company,
                        OfferTargetType::Brand,
                        OfferTargetType::Product => OfferFormHelper::targetOptions(
                            OfferTargetType::from((string) $get('target_type'))
                        ),
                        default => [],
                    })
                    ->searchable()
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('target_type')))
                    ->unique(
                        table: OfferTarget::class,
                        column: 'target_id',
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                            return $rule
                                ->where('offer_id', $this->getOwnerRecord()->getKey())
                                ->where('target_type', (string) $get('target_type'));
                        },
                    )
                    ->validationMessages([
                        'unique' => 'This target is already attached to the offer.',
                    ])
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('target_type')
                    ->badge()
                    ->formatStateUsing(fn (OfferTargetType $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('target_label')
                    ->label('Target')
                    ->state(fn ($record): string => OfferFormHelper::targetLabel(
                        $record->target_type,
                        $record->target_id,
                    )),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
