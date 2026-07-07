<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Checkout\CheckoutSummaryRequest;
use App\Http\Requests\Api\Checkout\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
    ) {}

    public function summary(CheckoutSummaryRequest $request): JsonResponse
    {
        $summary = $this->checkoutService->buildSummary(
            $request->checkoutUser(),
            $request->validated(),
        );

        return $this->success($summary);
    }

    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->checkoutService->placeOrder(
            $request->checkoutUser(),
            $request->validated(),
        );

        return $this->success(
            OrderResource::make($order)->resolve(),
            'Order placed successfully.',
            Response::HTTP_CREATED,
        );
    }
}
