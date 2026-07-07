<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\GuestCustomer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CatalogService $catalogService,
        private readonly SettingsService $settingsService,
        private readonly AddressService $addressService,
        private readonly OrderService $orderService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildSummary(?User $user, array $input): array
    {
        $paymentMethod = PaymentMethod::from($input['payment_method']);
        $this->assertPaymentMethodAllowed($paymentMethod);

        $delivery = $this->resolveDeliveryDetails($user, $input);
        $lines = $this->resolveCheckoutLines($user, $input);

        return $this->composeSummaryPayload(
            lines: $lines,
            delivery: $delivery,
            paymentMethod: $paymentMethod,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function placeOrder(?User $user, array $input): \App\Models\Order
    {
        $paymentMethod = PaymentMethod::from($input['payment_method']);
        $this->assertPaymentMethodAllowed($paymentMethod);

        $delivery = $this->resolveDeliveryDetails($user, $input);

        return DB::transaction(function () use ($user, $input, $paymentMethod, $delivery): \App\Models\Order {
            $lines = $this->resolveCheckoutLines($user, $input, lockProducts: true);
            $totals = $this->calculateTotals($lines);

            $this->assertMinimumOrderAmount($totals['subtotal']);

            $guestCustomerId = null;

            if ($user === null) {
                $guestCustomer = GuestCustomer::query()->create([
                    'name' => $delivery['name'],
                    'phone' => $delivery['phone'],
                    'email' => $delivery['email'] ?? null,
                    'address' => $delivery['address'],
                    'city' => $delivery['city'] ?? null,
                ]);

                $guestCustomerId = $guestCustomer->id;
            }

            $order = $this->orderService->createOrder(
                user: $user,
                guestCustomerId: $guestCustomerId,
                delivery: $delivery,
                lines: $lines,
                subtotal: $totals['subtotal'],
                deliveryCharges: $totals['delivery_charges'],
                grandTotal: $totals['grand_total'],
                paymentMethod: $paymentMethod,
                notes: $input['notes'] ?? null,
            );

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $this->inventoryService->decrementForOrder(
                    product: $product->fresh(),
                    quantity: $line['quantity'],
                    orderId: $order->id,
                );
            }

            if ($user !== null) {
                $cart = $this->cartService->getOrCreateCart($user);
                $this->cartService->clearItems($cart);
            }

            return $order->fresh(['items', 'statusLogs']);
        });
    }

    public function resolveAuthenticatedUser(?string $bearerToken): ?User
    {
        if ($bearerToken === null || $bearerToken === '') {
            return null;
        }

        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
        $user = $token?->tokenable;

        if (! $user instanceof User) {
            throw new BusinessException(
                'Invalid or expired token.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($user->status !== UserStatus::Active) {
            throw new BusinessException(
                'Your account is not active.',
                Response::HTTP_FORBIDDEN,
            );
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{
     *     product_id: int,
     *     product_name: string,
     *     sku_code: string,
     *     quantity: int,
     *     unit_price: string,
     *     line_total: string,
     *     product: Product
     * }>
     */
    private function resolveCheckoutLines(?User $user, array $input, bool $lockProducts = false): array
    {
        if ($user !== null) {
            $cart = $this->cartService->getOrCreateCart($user);

            if ($lockProducts) {
                return $this->lockedCheckoutLinesFromCart($cart);
            }

            return $this->cartService->checkoutLinesFromCart($cart);
        }

        return $this->checkoutLinesFromGuestItems($input['items'] ?? [], $lockProducts);
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array{
     *     product_id: int,
     *     product_name: string,
     *     sku_code: string,
     *     quantity: int,
     *     unit_price: string,
     *     line_total: string,
     *     product: Product
     * }>
     */
    private function checkoutLinesFromGuestItems(array $items, bool $lockProducts): array
    {
        if ($items === []) {
            throw new BusinessException(
                'At least one item is required.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lines = [];

        foreach ($items as $item) {
            $product = $this->catalogService->findPurchasableProduct(
                (int) $item['product_id'],
                lockForUpdate: $lockProducts,
            );
            $quantity = (int) $item['quantity'];
            $this->catalogService->assertStockAvailable($product, $quantity);

            $unitPrice = $this->catalogService->salePrice($product);

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku_code' => $product->sku_code,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($unitPrice, (string) $quantity, 2),
                'product' => $product,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{
     *     product_id: int,
     *     product_name: string,
     *     sku_code: string,
     *     quantity: int,
     *     unit_price: string,
     *     line_total: string,
     *     product: Product
     * }>
     */
    private function lockedCheckoutLinesFromCart(\App\Models\Cart $cart): array
    {
        $cart = $cart->load('items');

        if ($cart->items->isEmpty()) {
            throw new BusinessException(
                'Your cart is empty.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lines = [];

        foreach ($cart->items as $item) {
            $product = $this->catalogService->findPurchasableProduct($item->product_id, lockForUpdate: true);
            $this->catalogService->assertStockAvailable($product, $item->quantity);

            $unitPrice = $this->catalogService->salePrice($product);

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku_code' => $product->sku_code,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($unitPrice, (string) $item->quantity, 2),
                'product' => $product,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     address: string,
     *     city?: string|null,
     *     area?: string|null
     * }
     */
    private function resolveDeliveryDetails(?User $user, array $input): array
    {
        if ($user !== null && isset($input['address_id'])) {
            $address = $this->addressService->findForUser($user, (int) $input['address_id']);

            return [
                'name' => $address->name,
                'phone' => $address->phone,
                'email' => $user->email,
                'address' => $address->address,
                'city' => $address->city,
                'area' => $address->area,
            ];
        }

        return [
            'name' => (string) $input['name'],
            'phone' => (string) $input['phone'],
            'email' => $input['email'] ?? ($user?->email),
            'address' => (string) $input['address'],
            'city' => $input['city'] ?? null,
            'area' => $input['area'] ?? null,
        ];
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
     * @return array<string, mixed>
     */
    private function composeSummaryPayload(array $lines, array $delivery, PaymentMethod $paymentMethod): array
    {
        $totals = $this->calculateTotals($lines);
        $minOrderAmount = $this->settingsService->minOrderAmount();

        $this->assertMinimumOrderAmount($totals['subtotal']);

        return [
            'subtotal' => $totals['subtotal'],
            'delivery_charges' => $totals['delivery_charges'],
            'discount_amount' => '0.00',
            'grand_total' => $totals['grand_total'],
            'free_delivery_applied' => $totals['free_delivery_applied'],
            'min_order_amount' => $minOrderAmount,
            'min_order_met' => bccomp($totals['subtotal'], (string) $minOrderAmount, 2) >= 0,
            'currency' => $this->settingsService->currency(),
            'payment_method' => $paymentMethod->value,
            'delivery' => [
                'name' => $delivery['name'],
                'phone' => $delivery['phone'],
                'email' => $delivery['email'] ?? null,
                'address' => $delivery['address'],
                'city' => $delivery['city'] ?? null,
                'area' => $delivery['area'] ?? null,
            ],
            'items' => collect($lines)->map(fn (array $line): array => [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'sku_code' => $line['sku_code'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<array{line_total: string}>  $lines
     * @return array{
     *     subtotal: string,
     *     delivery_charges: string,
     *     grand_total: string,
     *     free_delivery_applied: bool
     * }
     */
    private function calculateTotals(array $lines): array
    {
        $subtotal = collect($lines)->reduce(
            fn (string $carry, array $line): string => bcadd($carry, $line['line_total'], 2),
            '0.00',
        );

        $freeDeliveryMin = (string) $this->settingsService->freeDeliveryMinAmount();
        $freeDeliveryApplied = bccomp($subtotal, $freeDeliveryMin, 2) >= 0;
        $deliveryCharges = $freeDeliveryApplied
            ? '0.00'
            : sprintf('%.2f', $this->settingsService->deliveryCharges());

        $grandTotal = bcadd($subtotal, $deliveryCharges, 2);

        return [
            'subtotal' => $subtotal,
            'delivery_charges' => $deliveryCharges,
            'grand_total' => $grandTotal,
            'free_delivery_applied' => $freeDeliveryApplied,
        ];
    }

    private function assertMinimumOrderAmount(string $subtotal): void
    {
        $minOrderAmount = (string) $this->settingsService->minOrderAmount();

        if (bccomp($subtotal, $minOrderAmount, 2) < 0) {
            throw new BusinessException(
                "Minimum order amount is {$minOrderAmount} PKR.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function assertPaymentMethodAllowed(PaymentMethod $paymentMethod): void
    {
        match ($paymentMethod) {
            PaymentMethod::Cod => $this->assertCodEnabled(),
            PaymentMethod::Jazzcash => $this->assertJazzcashEnabled(),
            PaymentMethod::Easypaisa => $this->assertEasypaisaEnabled(),
        };
    }

    private function assertJazzcashEnabled(): void
    {
        if (! $this->settingsService->isJazzcashEnabled()) {
            throw new BusinessException(
                'JazzCash payments are currently unavailable.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function assertEasypaisaEnabled(): void
    {
        if (! $this->settingsService->isEasypaisaEnabled()) {
            throw new BusinessException(
                'Easypaisa payments are currently unavailable.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function assertCodEnabled(): void
    {
        if (! $this->settingsService->isCodEnabled()) {
            throw new BusinessException(
                'Cash on delivery is currently unavailable.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
