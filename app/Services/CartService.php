<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CartService
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    public function getOrCreateCart(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public function getCartForUser(User $user): Cart
    {
        $cart = $this->getOrCreateCart($user);

        return $this->loadCartRelations($cart);
    }

    /**
     * @param  array{product_id: int, quantity: int}  $data
     */
    public function addItem(User $user, array $data): Cart
    {
        return DB::transaction(function () use ($user, $data): Cart {
            $product = $this->catalogService->findPurchasableProduct($data['product_id'], lockForUpdate: true);
            $this->catalogService->assertStockAvailable($product, $data['quantity']);

            $cart = $this->getOrCreateCart($user);
            $price = $this->catalogService->salePrice($product);

            $existingItem = $cart->items()
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($existingItem !== null) {
                $newQuantity = $existingItem->quantity + $data['quantity'];
                $this->catalogService->assertStockAvailable($product, $newQuantity);

                $existingItem->update([
                    'quantity' => $newQuantity,
                    'price' => $price,
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $data['quantity'],
                    'price' => $price,
                ]);
            }

            return $this->loadCartRelations($cart->fresh());
        });
    }

    public function updateItem(User $user, CartItem $cartItem, int $quantity): Cart
    {
        $this->assertCartItemOwnedByUser($user, $cartItem);

        return DB::transaction(function () use ($user, $cartItem, $quantity): Cart {
            $product = $this->catalogService->findPurchasableProduct($cartItem->product_id, lockForUpdate: true);
            $this->catalogService->assertStockAvailable($product, $quantity);

            $cartItem->update([
                'quantity' => $quantity,
                'price' => $this->catalogService->salePrice($product),
            ]);

            return $this->loadCartRelations($cartItem->cart->fresh());
        });
    }

    public function removeItem(User $user, CartItem $cartItem): Cart
    {
        $this->assertCartItemOwnedByUser($user, $cartItem);

        $cart = $cartItem->cart;
        $cartItem->delete();

        return $this->loadCartRelations($cart->fresh());
    }

    public function clearCart(User $user): Cart
    {
        $cart = $this->getOrCreateCart($user);
        $cart->items()->delete();

        return $this->loadCartRelations($cart->fresh());
    }

    /**
     * @return list<array{
     *     product_id: int,
     *     product_name: string,
     *     sku_code: string,
     *     quantity: int,
     *     unit_price: string,
     *     line_total: string
     * }>
     */
    public function checkoutLinesFromCart(Cart $cart): array
    {
        $cart = $this->loadCartRelations($cart);

        if ($cart->items->isEmpty()) {
            throw new BusinessException(
                'Your cart is empty.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $lines = [];

        foreach ($cart->items as $item) {
            $product = $this->catalogService->findPurchasableProduct($item->product_id);
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

    public function clearItems(Cart $cart): void
    {
        $cart->items()->delete();
    }

    private function loadCartRelations(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.brand',
            'items.product.company',
        ]);
    }

    private function assertCartItemOwnedByUser(User $user, CartItem $cartItem): void
    {
        $cartItem->loadMissing('cart');

        if ($cartItem->cart->user_id !== $user->id) {
            throw new BusinessException(
                'Cart item not found.',
                Response::HTTP_NOT_FOUND,
            );
        }
    }
}
