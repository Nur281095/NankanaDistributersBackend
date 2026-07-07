<?php

use App\Enums\AdminStatus;
use App\Enums\InventoryLogType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Gate;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        AdminSeeder::class,
        SettingsSeeder::class,
        DemoCatalogSeeder::class,
    ]);

    $this->admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
    $this->product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
    $this->user = User::factory()->create(['phone' => '03009998877']);
    $this->orderId = placeCodOrder($this->user, $this->product, 2);
    $this->order = Order::query()->findOrFail($this->orderId);
});

describe('OrderService admin lifecycle', function (): void {
    it('advances an order through packed, on way, and delivered', function (): void {
        $service = app(OrderService::class);

        $service->advanceOrderStatus($this->order, OrderStatus::Packed, $this->admin);
        expect($this->order->fresh()->order_status)->toBe(OrderStatus::Packed);

        $service->advanceOrderStatus($this->order->fresh(), OrderStatus::OnWay, $this->admin);
        expect($this->order->fresh()->order_status)->toBe(OrderStatus::OnWay);

        $service->advanceOrderStatus($this->order->fresh(), OrderStatus::Delivered, $this->admin);
        $delivered = $this->order->fresh();

        expect($delivered->order_status)->toBe(OrderStatus::Delivered);
        expect($delivered->delivered_at)->not->toBeNull();
        expect($delivered->statusLogs)->toHaveCount(4);
    });

    it('rejects invalid status transitions', function (): void {
        expect(fn () => app(OrderService::class)->advanceOrderStatus(
            $this->order,
            OrderStatus::Delivered,
            $this->admin,
        ))->toThrow(BusinessException::class, 'Cannot change order status');
    });

    it('cancels an order as admin and restores stock', function (): void {
        $stockAfterOrder = $this->product->fresh()->stock_quantity;

        app(OrderService::class)->advanceOrderStatus(
            $this->order,
            OrderStatus::Packed,
            $this->admin,
        );

        $cancelled = app(OrderService::class)->cancelOrderByAdmin(
            $this->order->fresh(),
            $this->admin,
            'Customer requested cancellation by phone.',
        );

        expect($cancelled->order_status)->toBe(OrderStatus::Cancelled);
        expect($cancelled->payment_status)->toBe(OrderPaymentStatus::Refunded);
        expect($cancelled->cancelled_at)->not->toBeNull();
        expect($this->product->fresh()->stock_quantity)->toBe($stockAfterOrder + 2);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $this->product->id,
            'type' => InventoryLogType::OrderCancelled->value,
            'reference_id' => $this->orderId,
        ]);
    });

    it('marks cod as received and updates payment records', function (): void {
        expect($this->order->payment_status)->toBe(OrderPaymentStatus::CodPending);

        $updated = app(OrderService::class)->markCodReceived(
            $this->order,
            $this->admin,
            'Cash collected on delivery.',
        );

        expect($updated->payment_status)->toBe(OrderPaymentStatus::Paid);
        expect($updated->admin_note)->toBe('Cash collected on delivery.');

        $payment = Payment::query()->where('order_id', $this->orderId)->firstOrFail();
        expect($payment->payment_status)->toBe(PaymentStatus::Paid);
        expect($payment->paid_at)->not->toBeNull();
    });

    it('rejects cod confirmation for non-cod orders', function (): void {
        $this->order->update([
            'payment_method' => PaymentMethod::Jazzcash,
            'payment_status' => OrderPaymentStatus::Pending,
        ]);

        expect(fn () => app(OrderService::class)->markCodReceived(
            $this->order->fresh(),
            $this->admin,
        ))->toThrow(BusinessException::class, 'COD payment cannot be marked as received');
    });

    it('rejects admin cancellation for delivered orders', function (): void {
        $service = app(OrderService::class);
        $service->advanceOrderStatus($this->order, OrderStatus::Packed, $this->admin);
        $service->advanceOrderStatus($this->order->fresh(), OrderStatus::OnWay, $this->admin);
        $service->advanceOrderStatus($this->order->fresh(), OrderStatus::Delivered, $this->admin);

        expect(fn () => $service->cancelOrderByAdmin(
            $this->order->fresh(),
            $this->admin,
        ))->toThrow(BusinessException::class, 'can no longer be cancelled');
    });

    it('returns allowed next statuses for active orders', function (): void {
        $statuses = app(OrderService::class)->allowedNextStatuses($this->order);

        expect($statuses)->toBe([
            OrderStatus::Packed,
            OrderStatus::Cancelled,
        ]);
    });
});

describe('Admin order and payment policies', function (): void {
    it('allows active admins to view and update orders and view payments', function (): void {
        $payment = Payment::query()->where('order_id', $this->orderId)->firstOrFail();

        expect(Gate::forUser($this->admin)->allows('viewAny', Order::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('view', $this->order))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $this->order))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('create', Order::class))->toBeFalse();

        expect(Gate::forUser($this->admin)->allows('viewAny', Payment::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('view', $payment))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $payment))->toBeFalse();
    });

    it('still allows customers to view and cancel their own orders', function (): void {
        expect(Gate::forUser($this->user)->allows('view', $this->order))->toBeTrue();
        expect(Gate::forUser($this->user)->allows('cancel', $this->order))->toBeTrue();
    });

    it('denies inactive admins order and payment access', function (): void {
        $this->admin->update(['status' => AdminStatus::Inactive]);
        $payment = Payment::query()->where('order_id', $this->orderId)->firstOrFail();

        expect(Gate::forUser($this->admin)->allows('viewAny', Order::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('view', $this->order))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', Payment::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('view', $payment))->toBeFalse();
    });
});

describe('Customer API compatibility', function (): void {
    it('still enforces the customer cancellation window', function (): void {
        Order::query()->whereKey($this->orderId)->update([
            'cancellation_deadline' => now()->subMinute(),
        ]);

        $response = $this->postJson(
            "/api/v1/orders/{$this->orderId}/cancel",
            [],
            authApiHeaders($this->user),
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The cancellation window has expired.');
    });
});
