<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\EasypaisaGateway;
use App\Services\Payments\JazzCashGateway;
use App\Support\Payments\EasypaisaSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaymentGatewayService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly SettingsService $settingsService,
        private readonly JazzCashGateway $jazzCashGateway,
        private readonly EasypaisaGateway $easypaisaGateway,
        private readonly PaymentNotificationService $paymentNotificationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function initiate(User $user, int $orderId, PaymentMethod $paymentMethod): array
    {
        $order = $this->orderService->findForUser($user, $orderId);

        $this->assertOnlinePaymentAllowed($order, $paymentMethod);

        return DB::transaction(function () use ($order, $paymentMethod): array {
            $payment = $this->resolvePendingPayment($order, $paymentMethod);
            $reference = $this->generateGatewayReference($order, $paymentMethod);

            $payment->update([
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatus::Pending,
                'gateway_reference' => $reference,
                'transaction_id' => null,
                'gateway_response' => null,
                'failure_reason' => null,
                'paid_at' => null,
                'failed_at' => null,
            ]);

            $order->update([
                'payment_method' => $paymentMethod,
                'payment_status' => OrderPaymentStatus::Pending,
            ]);

            return match ($paymentMethod) {
                PaymentMethod::Jazzcash => $this->jazzCashGateway->buildInitiationPayload(
                    order: $order->fresh(),
                    payment: $payment->fresh(),
                    reference: $reference,
                ),
                PaymentMethod::Easypaisa => $this->easypaisaGateway->buildInitiationPayload(
                    order: $order->fresh(),
                    payment: $payment->fresh(),
                    reference: $reference,
                ),
                PaymentMethod::Cod => throw new BusinessException(
                    'Cash on delivery does not require online payment initiation.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                ),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleCallback(string $gateway, array $payload): array
    {
        $normalizedGateway = strtolower($gateway);

        return match ($normalizedGateway) {
            'jazzcash' => $this->handleJazzCashCallback($payload),
            'easypaisa' => $this->handleEasypaisaCallback($payload),
            default => throw new BusinessException(
                'Unsupported payment gateway.',
                Response::HTTP_NOT_FOUND,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleJazzCashCallback(array $payload): array
    {
        $parsed = $this->jazzCashGateway->parseCallback($payload);
        $payment = $this->findPaymentByReference($parsed['reference'], PaymentMethod::Jazzcash);

        $this->assertPaymentAmount($payment, $parsed['amount']);

        if ($this->jazzCashGateway->isSuccessfulResponse($parsed['response_code'])) {
            if ($payment->payment_status !== PaymentStatus::Paid) {
                $order = $this->orderService->markOnlinePaymentPaid(
                    payment: $payment,
                    transactionId: $parsed['transaction_id'],
                    gatewayResponse: $parsed['payload'],
                );

                $this->paymentNotificationService->notifyPaymentSuccess($order);
            }

            return [
                'gateway' => 'jazzcash',
                'status' => 'paid',
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'reference' => $parsed['reference'],
            ];
        }

        if ($payment->payment_status !== PaymentStatus::Paid) {
            $reason = $parsed['response_message'] ?? 'JazzCash payment failed.';
            $order = $this->orderService->markOnlinePaymentFailed(
                payment: $payment,
                reason: $reason,
                gatewayResponse: $parsed['payload'],
                transactionId: $parsed['transaction_id'],
            );

            $this->paymentNotificationService->notifyPaymentFailure($order, $reason);
        }

        return [
            'gateway' => 'jazzcash',
            'status' => 'failed',
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'reference' => $parsed['reference'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleEasypaisaCallback(array $payload): array
    {
        $parsed = $this->easypaisaGateway->parseCallback($payload);
        $payment = $this->findPaymentByReference($parsed['reference'], PaymentMethod::Easypaisa);
        $isSuccessful = $this->easypaisaGateway->isSuccessfulCallback(
            $parsed['status'],
            $parsed['response_code'],
        );

        if ($isSuccessful) {
            if ($parsed['amount'] === null) {
                throw new BusinessException(
                    'Easypaisa success callback is missing payment amount.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $this->assertPaymentAmount($payment, $parsed['amount']);
        } elseif ($parsed['amount'] !== null) {
            $this->assertPaymentAmount($payment, $parsed['amount']);
        }

        if ($isSuccessful) {
            if ($payment->payment_status !== PaymentStatus::Paid) {
                $order = $this->orderService->markOnlinePaymentPaid(
                    payment: $payment,
                    transactionId: $parsed['transaction_id'],
                    gatewayResponse: $parsed['payload'],
                );

                $this->paymentNotificationService->notifyPaymentSuccess($order);
            }

            return [
                'gateway' => 'easypaisa',
                'status' => 'paid',
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'reference' => $parsed['reference'],
            ];
        }

        if ($payment->payment_status !== PaymentStatus::Paid) {
            $reason = $parsed['response_message'] ?? 'Easypaisa payment failed.';
            $order = $this->orderService->markOnlinePaymentFailed(
                payment: $payment,
                reason: $reason,
                gatewayResponse: $parsed['payload'],
                transactionId: $parsed['transaction_id'],
            );

            $this->paymentNotificationService->notifyPaymentFailure($order, $reason);
        }

        return [
            'gateway' => 'easypaisa',
            'status' => 'failed',
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'reference' => $parsed['reference'],
        ];
    }

    private function assertOnlinePaymentAllowed(Order $order, PaymentMethod $paymentMethod): void
    {
        if ($order->payment_method === PaymentMethod::Cod) {
            throw new BusinessException(
                'Online payment initiation is not required for cash on delivery orders.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (! in_array($order->payment_status, [
            OrderPaymentStatus::Pending,
            OrderPaymentStatus::Failed,
        ], true)) {
            throw new BusinessException(
                'This order is not awaiting online payment.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        match ($paymentMethod) {
            PaymentMethod::Jazzcash => $this->assertJazzcashEnabled(),
            PaymentMethod::Easypaisa => $this->assertEasypaisaEnabled(),
            PaymentMethod::Cod => throw new BusinessException(
                'Cash on delivery does not require online payment initiation.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ),
        };

        if ($order->payment_method !== $paymentMethod) {
            throw new BusinessException(
                'Payment method does not match the order.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function assertJazzcashEnabled(): void
    {
        if (! $this->settingsService->isJazzcashEnabled()) {
            throw new BusinessException(
                'JazzCash payments are currently unavailable.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->jazzCashGateway->assertConfigured();
    }

    private function assertEasypaisaEnabled(): void
    {
        if (! $this->settingsService->isEasypaisaEnabled()) {
            throw new BusinessException(
                'Easypaisa payments are currently unavailable.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->easypaisaGateway->assertConfigured();
    }

    private function resolvePendingPayment(Order $order, PaymentMethod $paymentMethod): Payment
    {
        $payment = $order->payments()
            ->where('payment_method', $paymentMethod)
            ->latest('id')
            ->first();

        if ($payment === null) {
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatus::Pending,
                'amount' => $order->grand_total,
                'currency' => $this->settingsService->currency(),
            ]);
        }

        return $payment;
    }

    private function generateGatewayReference(Order $order, PaymentMethod $paymentMethod): string
    {
        $prefix = match ($paymentMethod) {
            PaymentMethod::Jazzcash => 'JC',
            PaymentMethod::Easypaisa => 'EP',
            PaymentMethod::Cod => 'COD',
        };

        return Str::upper($prefix.$order->id.now()->format('ymdHis').Str::upper(Str::random(4)));
    }

    private function findPaymentByReference(string $reference, PaymentMethod $paymentMethod): Payment
    {
        $payment = Payment::query()
            ->where('gateway_reference', $reference)
            ->where('payment_method', $paymentMethod)
            ->first();

        if ($payment === null) {
            throw new BusinessException(
                'Payment reference not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $payment;
    }

    private function assertPaymentAmount(Payment $payment, string $gatewayAmount): void
    {
        $expected = number_format((float) $payment->amount, 2, '.', '');

        if (! EasypaisaSignature::amountMatches($expected, $gatewayAmount)) {
            throw new BusinessException(
                'Payment amount mismatch.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
