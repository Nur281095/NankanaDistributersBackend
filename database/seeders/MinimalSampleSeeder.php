<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Enums\InventoryLogType;
use App\Enums\NotificationType;
use App\Enums\OfferTargetType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettingType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Company;
use App\Models\CustomerAddress;
use App\Models\DeviceToken;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\GuestCustomer;
use App\Models\InventoryLog;
use App\Models\Offer;
use App\Models\OfferTarget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MinimalSampleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $admin = Admin::query()->create([
            'name' => 'Sample Admin',
            'email' => 'admin@nankanadistributors.com',
            'password' => Hash::make('password'),
            'role' => AdminRole::Admin,
            'status' => AdminStatus::Active,
        ]);

        $user = User::query()->create([
            'name' => 'Sample Customer',
            'email' => 'customer@example.com',
            'phone' => '03001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'customer@example.com',
            'token' => hash('sha256', 'sample-reset-token'),
            'created_at' => $now,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SampleSeeder/1.0',
            'payload' => base64_encode('sample-session'),
            'last_activity' => $now->timestamp,
        ]);

        DB::table('cache')->insert([
            'key' => 'sample_cache_key',
            'value' => 'sample-value',
            'expiration' => $now->copy()->addHour()->timestamp,
        ]);

        DB::table('cache_locks')->insert([
            'key' => 'sample_cache_lock',
            'owner' => 'sample-owner',
            'expiration' => $now->copy()->addHour()->timestamp,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'sample']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now->timestamp,
            'created_at' => $now->timestamp,
        ]);

        DB::table('job_batches')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'sample-batch',
            'total_jobs' => 1,
            'pending_jobs' => 1,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'cancelled_at' => null,
            'created_at' => $now->timestamp,
            'finished_at' => null,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'failed-sample']),
            'exception' => 'Sample failed job exception',
            'failed_at' => $now,
        ]);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'sample-token',
            'token' => hash('sha256', 'sample-api-token'),
            'abilities' => '["*"]',
            'last_used_at' => null,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $guest = GuestCustomer::query()->create([
            'name' => 'Sample Guest',
            'phone' => '03007654321',
            'email' => 'guest@example.com',
            'address' => '456 Guest Avenue, Lahore',
            'city' => 'Lahore',
        ]);

        CustomerAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'Home',
            'name' => 'Sample Customer',
            'phone' => '03001234567',
            'address' => '123 Sample Street, Lahore',
            'city' => 'Lahore',
            'area' => 'Gulberg',
            'is_default' => true,
        ]);

        $company = Company::query()->create([
            'name' => 'Sample Company',
            'slug' => 'sample-company',
            'description' => 'Sample company for SQL export.',
            'status' => CatalogStatus::Active,
            'sort_order' => 1,
        ]);

        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Sample Brand',
            'slug' => 'sample-brand',
            'description' => 'Sample brand for SQL export.',
            'status' => CatalogStatus::Active,
            'sort_order' => 1,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'brand_id' => $brand->id,
            'name' => 'Sample Product',
            'slug' => 'sample-product',
            'sku_code' => 'SAMPLE-001',
            'description' => 'Sample product for SQL export.',
            'regular_price' => 1000.00,
            'sale_price' => 900.00,
            'purchase_price' => 700.00,
            'stock_quantity' => 50,
            'low_stock_threshold' => 5,
            'unit' => '1pc',
            'status' => CatalogStatus::Active,
            'is_featured' => true,
            'is_suggested' => false,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'products/sample.jpg',
            'sort_order' => 1,
        ]);

        InventoryLog::query()->create([
            'product_id' => $product->id,
            'admin_id' => $admin->id,
            'type' => InventoryLogType::Added,
            'old_quantity' => 0,
            'new_quantity' => 50,
            'quantity_difference' => 50,
            'note' => 'Initial sample stock',
        ]);

        $cart = Cart::query()->create([
            'user_id' => $user->id,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 900.00,
        ]);

        $order = Order::query()->create([
            'order_number' => 'ND-'.now()->format('Ymd').'-0001',
            'user_id' => $user->id,
            'guest_customer_id' => null,
            'is_guest' => false,
            'customer_name' => 'Sample Customer',
            'customer_phone' => '03001234567',
            'customer_email' => 'customer@example.com',
            'delivery_address' => '123 Sample Street, Lahore',
            'city' => 'Lahore',
            'area' => 'Gulberg',
            'subtotal' => 1800.00,
            'delivery_charges' => 150.00,
            'discount_amount' => 0.00,
            'grand_total' => 1950.00,
            'payment_method' => PaymentMethod::Cod->value,
            'payment_status' => OrderPaymentStatus::CodPending->value,
            'order_status' => OrderStatus::Received->value,
            'notes' => 'Sample order',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku_code' => $product->sku_code,
            'quantity' => 2,
            'unit_price' => 900.00,
            'total_price' => 1800.00,
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::Cod->value,
            'payment_status' => PaymentStatus::Pending->value,
            'amount' => 1950.00,
            'currency' => 'PKR',
        ]);

        OrderStatusLog::query()->create([
            'order_id' => $order->id,
            'old_status' => null,
            'new_status' => OrderStatus::Received->value,
            'changed_by' => $admin->id,
            'changed_by_type' => 'admin',
            'note' => 'Sample order created',
        ]);

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'device_token' => 'sample-fcm-device-token',
            'platform' => 'android',
            'status' => 'active',
        ]);

        AppNotification::query()->create([
            'user_id' => $user->id,
            'admin_id' => null,
            'title' => 'Sample notification',
            'message' => 'Your sample order was placed.',
            'type' => NotificationType::Order,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'is_read' => false,
        ]);

        $template = EmailTemplate::query()->create([
            'name' => 'Sample Template',
            'slug' => 'sample_template',
            'subject' => 'Sample subject {order_number}',
            'body' => 'Hello {customer_name}, this is a sample email template.',
            'status' => CatalogStatus::Active,
        ]);

        EmailLog::query()->create([
            'recipient' => 'customer@example.com',
            'subject' => 'Sample email',
            'body' => 'Sample email body',
            'status' => 'sent',
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'sent_at' => $now,
        ]);

        $offer = Offer::query()->create([
            'title' => 'Sample Offer',
            'description' => 'Sample offer for SQL export.',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'start_date' => $now->toDateString(),
            'end_date' => $now->copy()->addMonth()->toDateString(),
            'status' => CatalogStatus::Active,
        ]);

        OfferTarget::query()->create([
            'offer_id' => $offer->id,
            'target_type' => OfferTargetType::Product,
            'target_id' => $product->id,
        ]);

        Setting::query()->create([
            'key' => 'business_name',
            'value' => 'Nankana Distributors',
            'type' => SettingType::String,
        ]);
    }
}
