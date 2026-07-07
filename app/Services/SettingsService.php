<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'app.public_settings';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Keys safe to expose via the public settings API.
     *
     * @var list<string>
     */
    public const PUBLIC_KEYS = [
        'business_name',
        'currency',
        'free_delivery_min_amount',
        'delivery_charges',
        'min_order_amount',
        'order_cancel_limit_minutes',
        'cod_enabled',
        'jazzcash_enabled',
        'easypaisa_enabled',
        'support_phone',
        'support_whatsapp',
        'support_email',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $settings = [];

            foreach (self::PUBLIC_KEYS as $key) {
                $settings[$key] = Setting::getValue($key);
            }

            return $settings;
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function updateValue(Setting $setting, mixed $value): Setting
    {
        $setting->update([
            'value' => $this->serializeValue($setting->type, $value),
        ]);

        $this->clearCache();

        return $setting->fresh();
    }

    public function serializeValue(SettingType $type, mixed $value): string
    {
        return match ($type) {
            SettingType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            SettingType::Integer => (string) (int) $value,
            SettingType::Json => json_encode($value) ?: '{}',
            default => (string) $value,
        };
    }

    public function freeDeliveryMinAmount(): int
    {
        return (int) $this->get('free_delivery_min_amount', 999);
    }

    public function deliveryCharges(): int
    {
        return (int) $this->get('delivery_charges', 150);
    }

    public function minOrderAmount(): int
    {
        return (int) $this->get('min_order_amount', 0);
    }

    public function orderCancelLimitMinutes(): int
    {
        return (int) $this->get('order_cancel_limit_minutes', 5);
    }

    public function currency(): string
    {
        return (string) $this->get('currency', 'PKR');
    }

    public function isCodEnabled(): bool
    {
        return (bool) $this->get('cod_enabled', true);
    }

    public function autoConfirmCod(): bool
    {
        return (bool) $this->get('auto_confirm_cod', false);
    }

    public function isJazzcashEnabled(): bool
    {
        return (bool) $this->get('jazzcash_enabled', false);
    }

    public function isEasypaisaEnabled(): bool
    {
        return (bool) $this->get('easypaisa_enabled', false);
    }
}
