<?php

use App\Enums\CatalogStatus;
use App\Enums\InventoryLogType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingsSeeder::class,
        DemoCatalogSeeder::class,
    ]);
});

function cartUser(): User
{
    return User::factory()->create([
        'phone' => '03009998877',
        'password' => Hash::make('password1'),
    ]);
}

function nidoProduct(): Product
{
    return Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
}

describe('Cart API', function (): void {
    it('returns an empty cart for a new user', function (): void {
        $user = cartUser();

        $response = $this->getJson('/api/v1/cart', authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', '0.00')
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    });

    it('adds a product to the cart with server-side pricing', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], authApiHeaders($user));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.price', '799.00')
            ->assertJsonPath('data.items.0.line_total', '1598.00')
            ->assertJsonPath('data.subtotal', '1598.00');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 799.00,
        ]);
    });

    it('merges quantity when the same product is added again', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ], authApiHeaders($user));

        $response->assertCreated()
            ->assertJsonPath('data.items', fn ($items) => count($items) === 1)
            ->assertJsonPath('data.items.0.quantity', 4);

        $this->assertDatabaseCount('cart_items', 1);
    });

    it('updates a cart item quantity', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $cartItem = CartItem::query()->firstOrFail();

        $response = $this->patchJson("/api/v1/cart/items/{$cartItem->id}", [
            'quantity' => 5,
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.subtotal', '3995.00');
    });

    it('removes a cart item', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $cartItem = CartItem::query()->firstOrFail();

        $response = $this->deleteJson("/api/v1/cart/items/{$cartItem->id}", [], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.subtotal', '0.00');

        $this->assertDatabaseCount('cart_items', 0);
    });

    it('clears the cart', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], authApiHeaders($user));

        $response = $this->deleteJson('/api/v1/cart', [], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseCount('cart_items', 0);
    });

    it('rejects unavailable products', function (): void {
        $user = cartUser();
        $product = nidoProduct();
        $product->update(['status' => CatalogStatus::Inactive]);

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    });

    it('rejects quantities above available stock', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => $product->stock_quantity + 1,
        ], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    });

    it('forbids updating another users cart item', function (): void {
        $owner = cartUser();
        $intruder = User::factory()->create(['phone' => '03005554433']);
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($owner));

        $cartItem = CartItem::query()->firstOrFail();

        Auth::forgetGuards();

        $response = $this->patchJson("/api/v1/cart/items/{$cartItem->id}", [
            'quantity' => 2,
        ], authApiHeaders($intruder));

        $response->assertForbidden();
    });

    it('forbids removing another users cart item', function (): void {
        $owner = cartUser();
        $intruder = User::factory()->create(['phone' => '03005554422']);
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($owner));

        $cartItem = CartItem::query()->firstOrFail();

        Auth::forgetGuards();

        $this->deleteJson("/api/v1/cart/items/{$cartItem->id}", [], authApiHeaders($intruder))
            ->assertForbidden();

        expect(CartItem::query()->whereKey($cartItem->id)->exists())->toBeTrue();
    });

    it('rejects cart quantities above the allowed maximum', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1000,
        ], authApiHeaders($user))->assertUnprocessable();
    });
});

describe('Checkout API', function (): void {
    it('returns a checkout summary for a logged-in cart', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $response = $this->postJson('/api/v1/checkout/summary', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Ali Khan',
            'phone' => '03009998877',
            'address' => 'House 12, Model Town',
            'city' => 'Lahore',
            'area' => 'Model Town',
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subtotal', '799.00')
            ->assertJsonPath('data.delivery_charges', '150.00')
            ->assertJsonPath('data.grand_total', '949.00')
            ->assertJsonPath('data.free_delivery_applied', false)
            ->assertJsonPath('data.payment_method', PaymentMethod::Cod->value);
    });

    it('applies free delivery when the subtotal threshold is met', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], authApiHeaders($user));

        $response = $this->postJson('/api/v1/checkout/summary', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Ali Khan',
            'phone' => '03009998877',
            'address' => 'House 12, Model Town',
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.subtotal', '1598.00')
            ->assertJsonPath('data.delivery_charges', '0.00')
            ->assertJsonPath('data.grand_total', '1598.00')
            ->assertJsonPath('data.free_delivery_applied', true);
    });

    it('places a COD order, decrements stock, and clears the cart', function (): void {
        $user = cartUser();
        $product = nidoProduct();
        $startingStock = $product->stock_quantity;

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], authApiHeaders($user));

        $response = $this->postJson('/api/v1/checkout/place-order', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Ali Khan',
            'phone' => '03009998877',
            'address' => 'House 12, Model Town',
            'city' => 'Lahore',
            'notes' => 'Call before delivery',
        ], authApiHeaders($user));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_status', OrderStatus::Received->value)
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::CodPending->value)
            ->assertJsonPath('data.grand_total', '1598.00')
            ->assertJsonPath('data.items.0.sku_code', 'NIDO-400G')
            ->assertJsonStructure(['data' => ['order_number', 'can_cancel', 'status_logs']]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'customer_phone' => '03009998877',
            'grand_total' => 1598.00,
            'payment_method' => PaymentMethod::Cod->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_method' => PaymentMethod::Cod->value,
            'payment_status' => PaymentStatus::Pending->value,
            'amount' => 1598.00,
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'type' => InventoryLogType::OrderPlaced->value,
            'quantity_difference' => -2,
        ]);

        expect($product->fresh()->stock_quantity)->toBe($startingStock - 2);
        expect(Cart::query()->where('user_id', $user->id)->first()?->items)->toHaveCount(0);
        expect(Order::query()->count())->toBe(1);
    });

    it('supports guest checkout with inline items', function (): void {
        $product = nidoProduct();
        $startingStock = $product->stock_quantity;

        $response = $this->postJson('/api/v1/checkout/place-order', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Guest Buyer',
            'phone' => '03001112233',
            'email' => 'guest@example.com',
            'address' => 'Shop 3, Main Bazaar',
            'city' => 'Lahore',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_guest', true)
            ->assertJsonPath('data.customer_name', 'Guest Buyer')
            ->assertJsonPath('data.grand_total', '949.00');

        $this->assertDatabaseHas('guest_customers', [
            'phone' => '03001112233',
            'email' => 'guest@example.com',
        ]);

        $this->assertDatabaseHas('orders', [
            'is_guest' => true,
            'user_id' => null,
            'grand_total' => 949.00,
        ]);

        expect($product->fresh()->stock_quantity)->toBe($startingStock - 1);
    });

    it('rejects online payment methods when disabled in settings', function (): void {
        Setting::query()->where('key', 'jazzcash_enabled')->update(['value' => '0']);

        app(SettingsService::class)->clearCache();

        $user = cartUser();
        $product = nidoProduct();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $response = $this->postJson('/api/v1/checkout/summary', [
            'payment_method' => PaymentMethod::Jazzcash->value,
            'name' => 'Ali Khan',
            'phone' => '03009998877',
            'address' => 'House 12, Model Town',
        ], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'JazzCash payments are currently unavailable.');
    });

    it('rejects checkout with an empty cart', function (): void {
        $user = cartUser();

        $response = $this->postJson('/api/v1/checkout/place-order', [
            'payment_method' => PaymentMethod::Cod->value,
            'name' => 'Ali Khan',
            'phone' => '03009998877',
            'address' => 'House 12, Model Town',
        ], authApiHeaders($user));

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Your cart is empty.');
    });

    it('uses a saved address for checkout summary', function (): void {
        $user = cartUser();
        $product = nidoProduct();

        $addressResponse = $this->postJson('/api/v1/addresses', [
            'name' => 'Ali Khan',
            'phone' => '03009998877',
            'address' => 'House 12, Model Town',
            'city' => 'Lahore',
            'is_default' => true,
        ], authApiHeaders($user));

        $addressId = $addressResponse->json('data.id');

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user));

        $response = $this->postJson('/api/v1/checkout/summary', [
            'payment_method' => PaymentMethod::Cod->value,
            'address_id' => $addressId,
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.delivery.address', 'House 12, Model Town')
            ->assertJsonPath('data.delivery.name', 'Ali Khan');
    });
});
