<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Jobs\SendAppNotificationJob;
use App\Jobs\SendTemplatedEmailJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentReportService;
use App\Services\Payments\JazzCashGateway;
use App\Support\Payments\JazzCashSignature;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingsSeeder::class,
        EmailTemplateSeeder::class,
        DemoCatalogSeeder::class,
    ]);

    configurePaymentGateways();
});

describe('JazzCashSignature', function (): void {
    it('matches the documented sandbox hash example', function (): void {
        $fields = [
            'pp_Amount' => '10000',
            'pp_MerchantID' => 'MC18746',
            'pp_Password' => 'a880tb6hat',
            'pp_TxnCurrency' => 'PKR',
            'pp_TxnRefNo' => 'TREF2022051812564132',
        ];

        $hash = JazzCashSignature::generate($fields, 'yxxsz9104y');

        expect($hash)->toBe('EC7D0A8D704E0E0652BF69838FD3DB156C2C6D2F8AEB082A65D64E2EAC139865');
    });
});

describe('Payment initiation', function (): void {
    it('returns jazzcash redirect fields for a pending online order', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Jazzcash);

        $response = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gateway', 'jazzcash')
            ->assertJsonPath('data.method', 'GET')
            ->assertJsonStructure([
                'data' => [
                    'action_url',
                    'redirect_url',
                    'method',
                    'reference',
                    'payment_id',
                    'fields' => [
                        'pp_TxnRefNo',
                        'pp_Amount',
                        'pp_SecureHash',
                    ],
                ],
            ]);

        expect($response->json('data.fields'))->not->toHaveKey('pp_Password');
        expect($response->json('data.redirect_url'))->toContain('/payments/jazzcash/checkout/');

        $checkout = $this->get($response->json('data.redirect_url'));
        $checkout->assertOk()
            ->assertSee('name="pp_Password"', false)
            ->assertSee('name="pp_SecureHash"', false);

        $this->get($response->json('data.redirect_url'))->assertStatus(410);

        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
            'payment_status' => PaymentStatus::Pending->value,
        ]);
    });

    it('returns easypaisa redirect fields for a pending online order', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Easypaisa);

        $response = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Easypaisa->value,
        ], authApiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.gateway', 'easypaisa')
            ->assertJsonStructure([
                'data' => [
                    'fields' => [
                        'storeId',
                        'amount',
                        'orderRefNum',
                        'merchantHashedReq',
                    ],
                ],
            ]);
    });
});

describe('JazzCash callbacks', function (): void {
    it('marks a payment as paid when callback signature and amount are valid', function (): void {
        Queue::fake();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email' => 'payer@example.com',
        ]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Jazzcash);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
        ], authApiHeaders($user))->assertOk();

        $reference = $initiate->json('data.reference');
        $order = Order::query()->findOrFail($orderId);
        $amount = app(JazzCashGateway::class)->formatAmount($order->grand_total);

        $response = $this->postJson('/api/v1/payments/callback/jazzcash', jazzCashCallbackPayload([
            'pp_TxnRefNo' => $reference,
            'pp_Amount' => $amount,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.order_id', $orderId);

        expect(Order::query()->find($orderId)?->payment_status)->toBe(OrderPaymentStatus::Paid);
        expect(Payment::query()->where('gateway_reference', $reference)->first()?->payment_status)
            ->toBe(PaymentStatus::Paid);

        Queue::assertPushed(SendAppNotificationJob::class);
        Queue::assertPushed(SendTemplatedEmailJob::class);
    });

    it('rejects callbacks with an invalid signature', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Jazzcash);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
        ], authApiHeaders($user))->assertOk();

        $payload = jazzCashCallbackPayload([
            'pp_TxnRefNo' => $initiate->json('data.reference'),
        ]);
        $payload['pp_SecureHash'] = 'INVALIDHASH';

        $this->postJson('/api/v1/payments/callback/jazzcash', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid JazzCash callback signature.');
    });

    it('marks a payment as failed and notifies the customer', function (): void {
        Queue::fake();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email' => 'payer@example.com',
        ]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Jazzcash);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
        ], authApiHeaders($user))->assertOk();

        $reference = $initiate->json('data.reference');
        $order = Order::query()->findOrFail($orderId);
        $amount = app(JazzCashGateway::class)->formatAmount($order->grand_total);

        $this->postJson('/api/v1/payments/callback/jazzcash', jazzCashCallbackPayload([
            'pp_TxnRefNo' => $reference,
            'pp_Amount' => $amount,
            'pp_ResponseCode' => '199',
            'pp_ResponseMessage' => 'Declined',
        ]))->assertOk()->assertJsonPath('data.status', 'failed');

        expect(Order::query()->find($orderId)?->payment_status)->toBe(OrderPaymentStatus::Failed);

        Queue::assertPushed(SendAppNotificationJob::class);
        Queue::assertPushed(SendTemplatedEmailJob::class);
    });

    it('is idempotent for duplicate successful callbacks', function (): void {
        Queue::fake();

        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Jazzcash);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Jazzcash->value,
        ], authApiHeaders($user))->assertOk();

        $reference = $initiate->json('data.reference');
        $order = Order::query()->findOrFail($orderId);
        $amount = app(JazzCashGateway::class)->formatAmount($order->grand_total);
        $payload = jazzCashCallbackPayload([
            'pp_TxnRefNo' => $reference,
            'pp_Amount' => $amount,
        ]);

        $this->postJson('/api/v1/payments/callback/jazzcash', $payload)->assertOk();
        $this->postJson('/api/v1/payments/callback/jazzcash', $payload)->assertOk();

        Queue::assertPushed(SendAppNotificationJob::class, 1);
        Queue::assertPushed(SendTemplatedEmailJob::class, 1);
    });
});

describe('Easypaisa callbacks', function (): void {
    it('marks a payment as paid on successful callback', function (): void {
        Queue::fake();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email' => 'payer@example.com',
        ]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Easypaisa);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Easypaisa->value,
        ], authApiHeaders($user))->assertOk();

        $reference = $initiate->json('data.reference');
        $order = Order::query()->findOrFail($orderId);

        $this->postJson('/api/v1/payments/callback/easypaisa', easypaisaCallbackPayload([
            'orderRefNum' => $reference,
            'amount' => number_format((float) $order->grand_total, 2, '.', ''),
        ]))->assertOk()->assertJsonPath('data.status', 'paid');

        expect(Order::query()->find($orderId)?->payment_status)->toBe(OrderPaymentStatus::Paid);

        Queue::assertPushed(SendAppNotificationJob::class);
        Queue::assertPushed(SendTemplatedEmailJob::class);
    });

    it('rejects easypaisa callbacks with an invalid signature', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Easypaisa);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Easypaisa->value,
        ], authApiHeaders($user))->assertOk();

        $payload = easypaisaCallbackPayload([
            'orderRefNum' => $initiate->json('data.reference'),
        ]);
        $payload['merchantHashedResp'] = 'INVALIDHASH';

        $this->postJson('/api/v1/payments/callback/easypaisa', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid Easypaisa callback signature.');
    });

    it('marks a payment as failed when easypaisa returns failure', function (): void {
        Queue::fake();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email' => 'payer@example.com',
        ]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        $orderId = placeOnlineOrder($user, $product, PaymentMethod::Easypaisa);

        $initiate = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $orderId,
            'payment_method' => PaymentMethod::Easypaisa->value,
        ], authApiHeaders($user))->assertOk();

        $reference = $initiate->json('data.reference');

        $this->postJson('/api/v1/payments/callback/easypaisa', easypaisaCallbackPayload([
            'orderRefNum' => $reference,
            'status' => 'Failure',
            'responseCode' => '0001',
            'desc' => 'Insufficient balance',
        ]))->assertOk()->assertJsonPath('data.status', 'failed');

        expect(Order::query()->find($orderId)?->payment_status)->toBe(OrderPaymentStatus::Failed);
    });
});

describe('Checkout online payment availability', function (): void {
    it('allows jazzcash checkout summary when enabled', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], authApiHeaders($user))->assertCreated();

        $this->postJson('/api/v1/checkout/summary', [
            'payment_method' => PaymentMethod::Jazzcash->value,
            'name' => $user->name,
            'phone' => $user->phone,
            'address' => 'House 12, Model Town',
        ], authApiHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.payment_method', PaymentMethod::Jazzcash->value);
    });
});

describe('PaymentReportService', function (): void {
    it('summarizes payment counts and paid totals', function (): void {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
        placeOnlineOrder($user, $product, PaymentMethod::Jazzcash);

        $summary = app(PaymentReportService::class)->summary();

        expect($summary['total_count'])->toBeGreaterThan(0);
        expect($summary['pending_count'])->toBeGreaterThan(0);
    });
});
