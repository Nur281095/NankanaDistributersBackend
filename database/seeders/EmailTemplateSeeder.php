<?php

namespace Database\Seeders;

use App\Enums\CatalogStatus;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Order Confirmation',
                'slug' => 'order_confirmation',
                'subject' => 'Order {order_number} confirmed',
                'body' => 'Hello {customer_name}, your order {order_number} has been placed successfully. Total: {order_total}. Payment method: {payment_method}.',
            ],
            [
                'name' => 'Order Cancellation',
                'slug' => 'order_cancellation',
                'subject' => 'Order {order_number} cancelled',
                'body' => 'Hello {customer_name}, your order {order_number} has been cancelled.',
            ],
            [
                'name' => 'Order Delivered',
                'slug' => 'order_delivered',
                'subject' => 'Order {order_number} delivered',
                'body' => 'Hello {customer_name}, your order {order_number} has been delivered to {delivery_address}.',
            ],
            [
                'name' => 'Payment Success',
                'slug' => 'payment_success',
                'subject' => 'Payment received for order {order_number}',
                'body' => 'Hello {customer_name}, payment for order {order_number} was successful. Total: {order_total}.',
            ],
            [
                'name' => 'Payment Failed',
                'slug' => 'payment_failed',
                'subject' => 'Payment failed for order {order_number}',
                'body' => 'Hello {customer_name}, payment for order {order_number} could not be completed. Please try again.',
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'password_reset',
                'subject' => 'Reset your password',
                'body' => 'Hello {customer_name}, reset your password using this link: {reset_link}. This link expires in 60 minutes.',
            ],
            [
                'name' => 'Admin New Order Alert',
                'slug' => 'admin_new_order',
                'subject' => 'New order {order_number} received',
                'body' => 'A new order {order_number} has been placed by {customer_name}. Total: {order_total}.',
            ],
            [
                'name' => 'Low Stock Alert',
                'slug' => 'low_stock_alert',
                'subject' => 'Low stock: {product_name}',
                'body' => '{product_name} ({sku_code}) is running low. Current stock: {stock_quantity}. Threshold: {low_stock_threshold}.',
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'status' => CatalogStatus::Active,
                ],
            );
        }
    }
}
