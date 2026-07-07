<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use App\Filament\Support\SettingsFormHelper;
use App\Enums\SettingType;
use App\Models\Setting;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        /** @var Setting $setting */
        $setting = $this->getRecord();

        return $form
            ->schema([
                Forms\Components\Section::make(SettingsFormHelper::label($setting->key))
                    ->description(SettingsFormHelper::helper($setting->key))
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->disabled(),
                        Forms\Components\TextInput::make('type')
                            ->disabled()
                            ->formatStateUsing(fn (SettingType $state): string => ucfirst($state->value)),
                        Forms\Components\Placeholder::make('visibility')
                            ->label('API visibility')
                            ->content(SettingsFormHelper::isPublic($setting->key)
                                ? 'Exposed via public settings API'
                                : 'Internal admin setting only'),
                        ...$this->valueFields($setting),
                    ]),
            ]);
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private function valueFields(Setting $setting): array
    {
        return match ($setting->type) {
            SettingType::Boolean => [
                Forms\Components\Toggle::make('value')
                    ->label('Value')
                    ->required(),
            ],
            SettingType::Integer => [
                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ],
            SettingType::Json => [
                Forms\Components\Textarea::make('value')
                    ->label('JSON value')
                    ->rows(6)
                    ->required(),
            ],
            default => [
                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->maxLength(255),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->type === SettingType::Boolean) {
            $data['value'] = filter_var($this->record->value, FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Setting $record */
        return app(SettingsService::class)->updateValue($record, $data['value']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
