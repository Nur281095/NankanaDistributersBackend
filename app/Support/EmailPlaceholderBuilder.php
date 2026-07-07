<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;

class EmailPlaceholderBuilder
{
    /**
     * @return array<string, string>
     */
    public static function forUser(User $user): array
    {
        return [
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'customer_email' => $user->email ?? '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forOrder(Order $order): array
    {
        $settings = app(SettingsService::class);
        $currency = $settings->currency();

        return [
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email ?? '',
            'order_number' => $order->order_number,
            'order_total' => self::formatMoney((float) $order->grand_total, $currency),
            'payment_method' => self::formatPaymentMethod($order->payment_method),
            'delivery_address' => self::formatDeliveryAddress($order),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forProduct(Product $product, ?int $stockQuantity = null): array
    {
        return [
            'product_name' => $product->name,
            'sku_code' => $product->sku_code,
            'stock_quantity' => (string) ($stockQuantity ?? $product->stock_quantity),
            'low_stock_threshold' => (string) $product->low_stock_threshold,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forPasswordReset(User $user, string $plainToken): array
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return array_merge(self::forUser($user), [
            'reset_token' => $plainToken,
            'reset_link' => $frontendUrl.'/reset-password?phone='.urlencode($user->phone).'&token='.urlencode($plainToken),
        ]);
    }

    /**
     * @param  array<string, string>  ...$groups
     * @return array<string, string>
     */
    public static function merge(array ...$groups): array
    {
        return array_merge(...$groups);
    }

    public static function formatMoney(float $amount, string $currency): string
    {
        return $currency.' '.number_format($amount, 2);
    }

    public static function formatPaymentMethod(?PaymentMethod $method): string
    {
        if ($method === null) {
            return '';
        }

        return match ($method) {
            PaymentMethod::Cod => 'Cash on Delivery',
            PaymentMethod::Jazzcash => 'JazzCash',
            PaymentMethod::Easypaisa => 'Easypaisa',
        };
    }

    public static function formatDeliveryAddress(Order $order): string
    {
        $parts = array_filter([
            $order->delivery_address,
            $order->area,
            $order->city,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Replace `{placeholder}` tokens in template text.
     *
     * @param  array<string, string>  $placeholders
     */
    public static function render(string $text, array $placeholders): string
    {
        $replacements = [];

        foreach ($placeholders as $key => $value) {
            $replacements['{'.$key.'}'] = $value;
        }

        return strtr($text, $replacements);
    }

    /**
     * @return list<string>
     */
    public static function missingPlaceholders(string $text, array $placeholders): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', $text, $matches);

        $required = array_unique($matches[1] ?? []);
        $missing = [];

        foreach ($required as $key) {
            if (! array_key_exists($key, $placeholders)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    public static function assertPlaceholders(string $subject, string $body, array $placeholders): void
    {
        $missing = array_unique(array_merge(
            self::missingPlaceholders($subject, $placeholders),
            self::missingPlaceholders($body, $placeholders),
        ));

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'Missing email placeholders: '.implode(', ', $missing),
            );
        }
    }
}
