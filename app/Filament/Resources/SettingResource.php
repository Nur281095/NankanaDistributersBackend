<?php

namespace App\Filament\Resources;

use App\Enums\SettingType;
use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Support\SettingsFormHelper;
use App\Models\Setting;
use App\Services\SettingsService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $modelLabel = 'setting';

    protected static ?string $recordTitleAttribute = 'key';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Setting')
                    ->formatStateUsing(fn (string $state): string => SettingsFormHelper::label($state))
                    ->description(fn (Setting $record): ?string => SettingsFormHelper::helper($record->key))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->badge()
                    ->state(fn (Setting $record): string => SettingsFormHelper::group($record->key))
                    ->sortable(false),
                Tables\Columns\TextColumn::make('value')
                    ->label('Current value')
                    ->formatStateUsing(function (Setting $record, ?string $state): string {
                        if ($record->type === SettingType::Boolean) {
                            return filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
                        }

                        return (string) $state;
                    })
                    ->limit(40),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (SettingType $state): string => Str::headline($state->value)),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public API')
                    ->boolean()
                    ->state(fn (Setting $record): bool => in_array($record->key, SettingsService::PUBLIC_KEYS, true)),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->options(collect(SettingsFormHelper::groups())->mapWithKeys(
                        fn (string $group): array => [$group => $group]
                    )->all())
                    ->query(function ($query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $keys = collect(SettingsFormHelper::keys())
                            ->filter(fn (string $key): bool => SettingsFormHelper::group($key) === $data['value'])
                            ->all();

                        return $query->whereIn('key', $keys);
                    }),
                Tables\Filters\TernaryFilter::make('public_api')
                    ->label('Public API')
                    ->queries(
                        true: fn ($query) => $query->whereIn('key', SettingsService::PUBLIC_KEYS),
                        false: fn ($query) => $query->whereNotIn('key', SettingsService::PUBLIC_KEYS),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
