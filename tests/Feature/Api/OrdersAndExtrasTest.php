<?php

use App\Enums\InventoryLogType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Auth;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingsSeeder::class,
        DemoCatalogSeeder::class,
    ]);
});

describe('Orders API', function (): void {
    it('lists the authenticated users orders with pagination', function (): void {
        $user = User::factory()->create(['phone' => '03007776655']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();

        placeCodOrder($user, $product);

        $response = $this->getJson('/api/v1/orders?per_page=10', authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'order_number', 'order_status', 'grand_total'],
                ],
            ]);
    });

    it('filters orders by status group', function (): void {
        $user = User::factory()->create(['phone' => '03006665544']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeCodOrder($user, $product);

        Order::query()->whereKey($orderId)->update([
            'order_status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        $current = $this->getJson('/api/v1/orders?status_group=current', authApiHeaders($user));
        $delivered = $this->getJson('/api/v1/orders?status_group=delivered', authApiHeaders($user));

        $current->assertOk()->assertJsonCount(0, 'data');
        $delivered->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_status', OrderStatus::Delivered->value);
    });

    it('shows order detail with items and status timeline', function (): void {
        $user = User::factory()->create(['phone' => '03005554433']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeCodOrder($user, $product, 2);

        $response = $this->getJson("/api/v1/orders/{$orderId}", authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.items.0.sku_code', 'NIDO-400G')
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonStructure(['data' => ['status_logs', 'cancellation_deadline']]);
    });

    it('cancels an order within the deadline and restores stock', function (): void {
        $user = User::factory()->create(['phone' => '03004443322']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $startingStock = $product->fresh()->stock_quantity;
        $orderId = placeCodOrder($user, $product, 2);

        expect($product->fresh()->stock_quantity)->toBe($startingStock - 2);

        $response = $this->postJson("/api/v1/orders/{$orderId}/cancel", [], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.order_status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::Refunded->value)
            ->assertJsonPath('data.can_cancel', false);

        expect($product->fresh()->stock_quantity)->toBe($startingStock);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'type' => InventoryLogType::OrderCancelled->value,
            'quantity_difference' => 2,
            'reference_id' => $orderId,
        ]);
    });

    it('rejects cancellation after the deadline', function (): void {
        $user = User::factory()->create(['phone' => '03003332211']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeCodOrder($user, $product);

        Order::query()->whereKey($orderId)->update([
            'cancellation_deadline' => now()->subMinute(),
        ]);

        $response = $this->postJson("/api/v1/orders/{$orderId}/cancel", [], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The cancellation window has expired.');
    });

    it('rejects cancellation when the order is no longer received', function (): void {
        $user = User::factory()->create(['phone' => '03002221100']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeCodOrder($user, $product);

        Order::query()->whereKey($orderId)->update([
            'order_status' => OrderStatus::Packed,
        ]);

        $response = $this->postJson("/api/v1/orders/{$orderId}/cancel", [], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'This order can no longer be cancelled.');
    });

    it('forbids viewing another users order', function (): void {
        $owner = User::factory()->create(['phone' => '03001110099']);
        $intruder = User::factory()->create(['phone' => '03001110088']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeCodOrder($owner, $product);

        Auth::forgetGuards();

        $response = $this->getJson("/api/v1/orders/{$orderId}", authApiHeaders($intruder));

        $response->assertForbidden();
    });
});

describe('Settings API', function (): void {
    it('returns public settings without internal keys', function (): void {
        $response = $this->getJson('/api/v1/settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.business_name', 'Nankana Distributors')
            ->assertJsonPath('data.currency', 'PKR')
            ->assertJsonPath('data.cod_enabled', true)
            ->assertJsonStructure([
                'data' => [
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
                ],
            ]);

        expect($response->json('data'))->not->toHaveKey('auto_confirm_cod');
    });
});

describe('Device Token API', function (): void {
    it('stores and upserts a device token for the authenticated user', function (): void {
        $user = User::factory()->create(['phone' => '03008887766']);

        $response = $this->postJson('/api/v1/devices/token', [
            'token' => 'fcm-token-abc123',
            'platform' => 'android',
        ], authApiHeaders($user));

        $response->assertCreated()
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-abc123',
        ]);

        $otherUser = User::factory()->create(['phone' => '03008887755']);
        Auth::forgetGuards();

        $this->postJson('/api/v1/devices/token', [
            'token' => 'fcm-token-abc123',
            'platform' => 'ios',
        ], authApiHeaders($otherUser))->assertCreated();

        $this->assertDatabaseHas('device_tokens', [
            'device_token' => 'fcm-token-abc123',
            'user_id' => $otherUser->id,
            'platform' => 'ios',
        ]);
    });

    it('removes a device token for the authenticated user', function (): void {
        $user = User::factory()->create(['phone' => '03007776611']);

        $this->postJson('/api/v1/devices/token', [
            'token' => 'fcm-token-remove-me',
            'platform' => 'android',
        ], authApiHeaders($user))->assertCreated();

        $response = $this->deleteJson('/api/v1/devices/token', [
            'token' => 'fcm-token-remove-me',
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('device_tokens', [
            'device_token' => 'fcm-token-remove-me',
        ]);
    });
});

describe('Payment API scaffold', function (): void {
    it('rejects online payment initiation for COD orders', function (): void {
        $user = User::factory()->create(['phone' => '03006661122']);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeCodOrder($user, $product);

        $response = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
        ], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Online payment initiation is not required for cash on delivery orders.');
    });

    it('processes jazzcash payment callbacks', function (): void {
        configurePaymentGateways();

        $response = $this->postJson('/api/v1/payments/callback/jazzcash', jazzCashCallbackPayload([
            'pp_TxnRefNo' => 'UNKNOWN-REF',
        ]));

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    });
});

describe('COD end-to-end flow', function (): void {
    it('completes register to cancel flow with stock restored', function (): void {
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $startingStock = $product->fresh()->stock_quantity;

        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Flow User',
            'phone' => '03001239999',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertCreated();

        $token = $register->json('data.token');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        $this->getJson('/api/v1/companies', $headers)->assertOk();
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], $headers)->assertCreated();

        $this->postJson('/api/v1/checkout/summary', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Flow User',
            'phone' => '03001239999',
            'address' => 'House 99, Test Town',
        ], $headers)->assertOk();

        $orderResponse = $this->postJson('/api/v1/checkout/place-order', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Flow User',
            'phone' => '03001239999',
            'address' => 'House 99, Test Town',
        ], $headers)->assertCreated();

        $orderId = $orderResponse->json('data.id');

        $this->getJson('/api/v1/orders', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/orders/{$orderId}", $headers)->assertOk();

        $this->postJson("/api/v1/orders/{$orderId}/cancel", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.order_status', OrderStatus::Cancelled->value);

        expect($product->fresh()->stock_quantity)->toBe($startingStock);
    });
});
