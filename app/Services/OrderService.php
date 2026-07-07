<?php

namespace App\Services;

use App\Enums\ChangedByType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrderService
{
    /**
     * @var array<string, list<OrderStatus>>
     */
    private const STATUS_GROUPS = [
        'current' => [
            OrderStatus::Received,
            OrderStatus::Packed,
            OrderStatus::OnWay,
        ],
        'delivered' => [
            OrderStatus::Delivered,
        ],
        'cancelled' => [
            OrderStatus::Cancelled,
        ],
    ];

    /**
     * @var array<string, list<OrderStatus>>
     */
    private const ADMIN_TRANSITIONS = [
        OrderStatus::Received->value => [
            OrderStatus::Packed,
            OrderStatus::Cancelled,
        ],
        OrderStatus::Packed->value => [
            OrderStatus::OnWay,
            OrderStatus::Cancelled,
        ],
        OrderStatus::OnWay->value => [
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
        ],
    ];

    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * @return list<OrderStatus>
     */
    public function allowedNextStatuses(Order $order): array
    {
        if ($order->order_status === null) {
            return [];
        }

        return self::ADMIN_TRANSITIONS[$order->order_status->value] ?? [];
    }

    public function canMarkCodReceived(Order $order): bool
    {
        return $order->payment_method === PaymentMethod::Cod
            && $order->payment_status === OrderPaymentStatus::CodPending
            && $order->order_status !== OrderStatus::Cancelled;
    }

    public function generateOrderNumber(): string
    {
        $latestId = Order::query()->lockForUpdate()->max('id') ?? 0;

        return sprintf('ORD-%06d', $latestId + 1);
    }

    public function paginateForUser(User $user, ?string $statusGroup, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->withCount('items')
            ->orderByDesc('created_at');

        if ($statusGroup !== null) {
            $statuses = self::STATUS_GROUPS[$statusGroup] ?? null;

            if ($statuses === null) {
                throw new BusinessException(
                    'Invalid status group. Allowed values: current, delivered, cancelled.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $query->whereIn('order_status', $statuses);
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function findForUser(User $user, int $orderId): Order
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->with(['items', 'statusLogs' => fn ($query) => $query->orderBy('created_at')])
            ->whereKey($orderId)
            ->first();

        if ($order === null) {
            throw new BusinessException(
                'Order not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $order;
    }

    public function cancelOrder(User $user, Order $order): Order
    {
        if ($order->user_id !== $user->id) {
            throw new BusinessException(
                'Order not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($order->order_status !== OrderStatus::Received) {
            throw new BusinessException(
                'This order can no longer be cancelled.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($order->cancellation_deadline === null || now()->greaterThan($order->cancellation_deadline)) {
            throw new BusinessException(
                'The cancellation window has expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return DB::transaction(function () use ($user, $order): Order {
            return $this->performCancellation(
                order: $order,
                changedBy: $user->id,
                changedByType: ChangedByType::Customer,
                note: 'Order cancelled by customer.',
            );
        });
    }

    public function advanceOrderStatus(
        Order $order,
        OrderStatus $newStatus,
        Admin $admin,
        ?string $note = null,
    ): Order {
        if ($order->order_status === null) {
            throw new BusinessException(
                'Order status is missing.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $allowed = self::ADMIN_TRANSITIONS[$order->order_status->value] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new BusinessException(
                sprintf(
                    'Cannot change order status from %s to %s.',
                    $order->order_status->value,
                    $newStatus->value,
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($newStatus === OrderStatus::Cancelled) {
            return $this->cancelOrderByAdmin($order, $admin, $note);
        }

        return DB::transaction(function () use ($order, $newStatus, $admin, $note): Order {
            $oldStatus = $order->order_status;

            $updates = ['order_status' => $newStatus];

            if ($newStatus === OrderStatus::Delivered) {
                $updates['delivered_at'] = now();
            }

            $order->update($updates);

            $this->recordStatusChange(
                order: $order,
                oldStatus: $oldStatus,
                newStatus: $newStatus,
                changedBy: $admin->id,
                changedByType: ChangedByType::Admin,
                note: $note ?? sprintf('Order marked as %s.', str_replace('_', ' ', $newStatus->value)),
            );

            return $order->fresh(['items', 'payments', 'statusLogs', 'user']);
        });
    }

    public function cancelOrderByAdmin(Order $order, Admin $admin, ?string $note = null): Order
    {
        if (! in_array($order->order_status, [
            OrderStatus::Received,
            OrderStatus::Packed,
            OrderStatus::OnWay,
        ], true)) {
            throw new BusinessException(
                'This order can no longer be cancelled.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return DB::transaction(function () use ($order, $admin, $note): Order {
            return $this->performCancellation(
                order: $order,
                changedBy: $admin->id,
                changedByType: ChangedByType::Admin,
                note: $note ?? 'Order cancelled by admin.',
            );
        });
    }

    public function markCodReceived(Order $order, Admin $admin, ?string $note = null): Order
    {
        if (! $this->canMarkCodReceived($order)) {
            throw new BusinessException(
                'COD payment cannot be marked as received for this order.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return DB::transaction(function () use ($order, $note): Order {
            $order->update([
                'payment_status' => OrderPaymentStatus::Paid,
            ]);

            $order->payments()
                ->where('payment_status', PaymentStatus::Pending)
                ->update([
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ]);

            if ($note !== null) {
                $order->update(['admin_note' => $note]);
            }

            return $order->fresh(['items', 'payments', 'statusLogs', 'user']);
        });
    }

    public function updateAdminNote(Order $order, ?string $adminNote): Order
    {
        $order->update(['admin_note' => $adminNote]);

        return $order->fresh(['items', 'payments', 'statusLogs']);
    }

    /**
     * @param  array<string, mixed>  $gatewayResponse
     */
    public function markOnlinePaymentPaid(
        Payment $payment,
        ?string $transactionId,
        array $gatewayResponse,
    ): Order {
        return DB::transaction(function () use ($payment, $transactionId, $gatewayResponse): Order {
            $payment->refresh();
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            if ($payment->payment_status === PaymentStatus::Paid) {
                return $order->fresh(['items', 'payments', 'statusLogs', 'user']);
            }

            $payment->update([
                'payment_status' => PaymentStatus::Paid,
                'transaction_id' => $transactionId,
                'gateway_response' => $gatewayResponse,
                'failure_reason' => null,
                'paid_at' => now(),
                'failed_at' => null,
            ]);

            $order->update([
                'payment_status' => OrderPaymentStatus::Paid,
            ]);

            return $order->fresh(['items', 'payments', 'statusLogs', 'user']);
        });
    }

    /**
     * @param  array<string, mixed>  $gatewayResponse
     */
    public function markOnlinePaymentFailed(
        Payment $payment,
        string $reason,
        array $gatewayResponse,
        ?string $transactionId = null,
    ): Order {
        return DB::transaction(function () use ($payment, $reason, $gatewayResponse, $transactionId): Order {
            $payment->refresh();
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            if ($payment->payment_status === PaymentStatus::Paid) {
                return $order->fresh(['items', 'payments', 'statusLogs', 'user']);
            }

            $payment->update([
                'payment_status' => PaymentStatus::Failed,
                'transaction_id' => $transactionId,
                'gateway_response' => $gatewayResponse,
                'failure_reason' => $reason,
                'failed_at' => now(),
            ]);

            $order->update([
                'payment_status' => OrderPaymentStatus::Failed,
            ]);

            return $order->fresh(['items', 'payments', 'statusLogs', 'user']);
        });
    }

    /**
     * @param  list<array{
     *     product_id: int,
     *     product_name: string,
     *     sku_code: string,
     *     quantity: int,
     *     unit_price: string,
     *     line_total: string,
     *     product: Product
     * }>  $lines
     * @param  array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     address: string,
     *     city?: string|null,
     *     area?: string|null
     * }  $delivery
     */
    public function createOrder(
        ?User $user,
        ?int $guestCustomerId,
        array $delivery,
        array $lines,
        string $subtotal,
        string $deliveryCharges,
        string $grandTotal,
        PaymentMethod $paymentMethod,
        ?string $notes = null,
    ): Order {
        $orderPaymentStatus = $this->resolveOrderPaymentStatus($paymentMethod);
        $paymentRecordStatus = $this->resolvePaymentRecordStatus($paymentMethod);
        $cancelLimitMinutes = $this->settingsService->orderCancelLimitMinutes();

        $order = Order::query()->create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user?->id,
            'guest_customer_id' => $guestCustomerId,
            'is_guest' => $user === null,
            'customer_name' => $delivery['name'],
            'customer_phone' => $delivery['phone'],
            'customer_email' => $delivery['email'] ?? null,
            'delivery_address' => $delivery['address'],
            'city' => $delivery['city'] ?? null,
            'area' => $delivery['area'] ?? null,
            'subtotal' => $subtotal,
            'delivery_charges' => $deliveryCharges,
            'discount_amount' => '0.00',
            'grand_total' => $grandTotal,
            'payment_method' => $paymentMethod,
            'payment_status' => $orderPaymentStatus,
            'order_status' => OrderStatus::Received,
            'notes' => $notes,
            'cancellation_deadline' => now()->addMinutes($cancelLimitMinutes),
        ]);

        foreach ($lines as $line) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'sku_code' => $line['sku_code'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'total_price' => $line['line_total'],
            ]);
        }

        Payment::query()->create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentRecordStatus,
            'amount' => $grandTotal,
            'currency' => $this->settingsService->currency(),
            'paid_at' => $paymentRecordStatus === PaymentStatus::Paid ? now() : null,
        ]);

        $this->recordStatusChange(
            order: $order,
            oldStatus: null,
            newStatus: OrderStatus::Received,
            changedBy: $user?->id,
            changedByType: $user !== null ? ChangedByType::Customer : ChangedByType::System,
            note: 'Order placed.',
        );

        return $order->load(['items', 'statusLogs']);
    }

    public function recordStatusChange(
        Order $order,
        ?OrderStatus $oldStatus,
        OrderStatus $newStatus,
        ?int $changedBy,
        ChangedByType $changedByType,
        ?string $note = null,
    ): OrderStatusLog {
        return OrderStatusLog::query()->create([
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'changed_by_type' => $changedByType,
            'note' => $note,
        ]);
    }

    private function performCancellation(
        Order $order,
        ?int $changedBy,
        ChangedByType $changedByType,
        string $note,
    ): Order {
        $this->restoreStockForCancelledOrder($order);

        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'payment_status' => OrderPaymentStatus::Refunded,
        ]);

        $order->payments()->update([
            'payment_status' => PaymentStatus::Refunded,
        ]);

        $this->recordStatusChange(
            order: $order,
            oldStatus: $oldStatus,
            newStatus: OrderStatus::Cancelled,
            changedBy: $changedBy,
            changedByType: $changedByType,
            note: $note,
        );

        return $order->fresh(['items', 'payments', 'statusLogs']);
    }

    private function restoreStockForCancelledOrder(Order $order): void
    {
        $order->load(['items.product']);

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $product = Product::query()
                ->lockForUpdate()
                ->whereKey($item->product_id)
                ->first();

            if ($product !== null) {
                $this->inventoryService->restoreForCancelledOrder(
                    product: $product,
                    quantity: $item->quantity,
                    orderId: $order->id,
                );
            }
        }
    }

    private function resolveOrderPaymentStatus(PaymentMethod $paymentMethod): OrderPaymentStatus
    {
        if ($paymentMethod !== PaymentMethod::Cod) {
            return OrderPaymentStatus::Pending;
        }

        return $this->settingsService->autoConfirmCod()
            ? OrderPaymentStatus::Paid
            : OrderPaymentStatus::CodPending;
    }

    private function resolvePaymentRecordStatus(PaymentMethod $paymentMethod): PaymentStatus
    {
        if ($paymentMethod !== PaymentMethod::Cod) {
            return PaymentStatus::Pending;
        }

        return $this->settingsService->autoConfirmCod()
            ? PaymentStatus::Paid
            : PaymentStatus::Pending;
    }
}
