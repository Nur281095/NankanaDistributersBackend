<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCartForUser($request->user());

        return $this->success(
            CartResource::make($cart)->resolve(),
        );
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            CartResource::make($cart)->resolve(),
            'Item added to cart.',
            Response::HTTP_CREATED,
        );
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $this->authorize('update', $cartItem);

        $cart = $this->cartService->updateItem(
            $request->user(),
            $cartItem,
            (int) $request->validated('quantity'),
        );

        return $this->success(
            CartResource::make($cart)->resolve(),
            'Cart item updated.',
        );
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorize('delete', $cartItem);

        $cart = $this->cartService->removeItem($request->user(), $cartItem);

        return $this->success(
            CartResource::make($cart)->resolve(),
            'Item removed from cart.',
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->clearCart($request->user());

        return $this->success(
            CartResource::make($cart)->resolve(),
            'Cart cleared.',
        );
    }
}
