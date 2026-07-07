<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Payment\InitiatePaymentRequest;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayService $paymentGatewayService,
    ) {}

    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $result = $this->paymentGatewayService->initiate(
            $request->user(),
            $request->orderId(),
            $request->paymentMethod(),
        );

        return $this->success($result);
    }

    public function callback(Request $request, string $gateway): JsonResponse
    {
        $result = $this->paymentGatewayService->handleCallback(
            $gateway,
            $request->all(),
        );

        return $this->success($result, 'Payment callback processed.');
    }
}
