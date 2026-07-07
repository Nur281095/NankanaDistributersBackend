<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Order\OrderListRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(OrderListRequest $request): JsonResponse
    {
        $orders = $this->orderService->paginateForUser(
            $request->user(),
            $request->statusGroup(),
            $request->page(),
            $request->perPage(),
        );

        return $this->paginated($orders, OrderResource::class);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order = $this->orderService->findForUser($request->user(), $order->id);

        return $this->success(
            OrderResource::make($order)->resolve(),
        );
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = $this->orderService->cancelOrder($request->user(), $order);

        return $this->success(
            OrderResource::make($order)->resolve(),
            'Order cancelled successfully.',
        );
    }
}
