<?php

namespace App\Filament\Support;

class SettingsFormHelper
{
    /**
     * @var array<string, array{label: string, group: string, helper?: string, public?: bool}>
     */
    private const METADATA = [
        'business_name' => [
            'label' => 'Business name',
            'group' => 'Business',
            'helper' => 'Displayed in the app and customer communications.',
            'public' => true,
        ],
        'currency' => [
            'label' => 'Currency code',
            'group' => 'Business',
            'helper' => 'ISO currency code used for order totals.',
            'public' => true,
        ],
        'free_delivery_min_amount' => [
            'label' => 'Free delivery minimum',
            'group' => 'Delivery',
            'helper' => 'Orders at or above this subtotal receive free delivery.',
            'public' => true,
        ],
        'delivery_charges' => [
            'label' => 'Standard delivery charges',
            'group' => 'Delivery',
            'helper' => 'Applied when the free delivery threshold is not met.',
            'public' => true,
        ],
        'min_order_amount' => [
            'label' => 'Minimum order amount',
            'group' => 'Orders',
            'helper' => 'Checkout is blocked when the cart subtotal is below this amount.',
            'public' => true,
        ],
        'order_cancel_limit_minutes' => [
            'label' => 'Customer cancel window (minutes)',
            'group' => 'Orders',
            'helper' => 'How long customers can cancel a received order after placement.',
            'public' => true,
        ],
        'cod_enabled' => [
            'label' => 'COD enabled',
            'group' => 'Payments',
            'helper' => 'Allow cash-on-delivery checkout in the mobile app.',
            'public' => true,
        ],
        'jazzcash_enabled' => [
            'label' => 'JazzCash enabled',
            'group' => 'Payments',
            'helper' => 'Show JazzCash as a payment option in the mobile app checkout.',
            'public' => true,
        ],
        'easypaisa_enabled' => [
            'label' => 'Easypaisa enabled',
            'group' => 'Payments',
            'helper' => 'Show Easypaisa as a payment option in the mobile app checkout.',
            'public' => true,
        ],
        'auto_confirm_cod' => [
            'label' => 'Auto-confirm COD payments',
            'group' => 'Payments',
            'helper' => 'Internal only. Marks COD orders as paid immediately on placement.',
            'public' => false,
        ],
        'support_phone' => [
            'label' => 'Support phone',
            'group' => 'Support',
            'helper' => 'Customer support phone number shown in the app.',
            'public' => true,
        ],
        'support_whatsapp' => [
            'label' => 'Support WhatsApp',
            'group' => 'Support',
            'helper' => 'WhatsApp number used for the floating support button.',
            'public' => true,
        ],
        'support_email' => [
            'label' => 'Support email',
            'group' => 'Support',
            'helper' => 'Customer support email shown in the app.',
            'public' => true,
        ],
    ];

    public static function label(string $key): string
    {
        return self::METADATA[$key]['label'] ?? str_replace('_', ' ', ucwords($key, '_'));
    }

    public static function group(string $key): string
    {
        return self::METADATA[$key]['group'] ?? 'General';
    }

    public static function helper(string $key): ?string
    {
        return self::METADATA[$key]['helper'] ?? null;
    }

    public static function isPublic(string $key): bool
    {
        return self::METADATA[$key]['public'] ?? false;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::METADATA);
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_map(
            fn (string $key): string => self::group($key),
            array_keys(self::METADATA),
        )));
    }
}
