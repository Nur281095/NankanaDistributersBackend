<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * @var array<string, array{value: string, type: SettingType}>
     */
    private array $settings = [
        'business_name' => ['value' => 'Nankana Distributors', 'type' => SettingType::String],
        'currency' => ['value' => 'PKR', 'type' => SettingType::String],
        'free_delivery_min_amount' => ['value' => '999', 'type' => SettingType::Integer],
        'delivery_charges' => ['value' => '150', 'type' => SettingType::Integer],
        'order_cancel_limit_minutes' => ['value' => '5', 'type' => SettingType::Integer],
        'min_order_amount' => ['value' => '0', 'type' => SettingType::Integer],
        'cod_enabled' => ['value' => '1', 'type' => SettingType::Boolean],
        'jazzcash_enabled' => ['value' => '1', 'type' => SettingType::Boolean],
        'easypaisa_enabled' => ['value' => '1', 'type' => SettingType::Boolean],
        'auto_confirm_cod' => ['value' => '0', 'type' => SettingType::Boolean],
        'support_phone' => ['value' => '03001234567', 'type' => SettingType::String],
        'support_whatsapp' => ['value' => '03001234567', 'type' => SettingType::String],
        'support_email' => ['value' => 'support@nankanadistributors.com', 'type' => SettingType::String],
    ];

    public function run(): void
    {
        foreach ($this->settings as $key => $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ],
            );
        }
    }
}
