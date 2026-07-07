<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\EasypaisaSignature;
use Symfony\Component\HttpFoundation\Response;

class EasypaisaGateway
{
    /**
     * @return array<string, mixed>
     */
    public function buildInitiationPayload(Order $order, Payment $payment, string $reference): array
    {
        $this->assertConfigured();

        $fields = [
            'storeId' => (string) config('payments.easypaisa.store_id'),
            'amount' => number_format((float) $order->grand_total, 2, '.', ''),
            'postBackURL' => $this->postbackUrl(),
            'orderRefNum' => $reference,
            'expiryDate' => now()
                ->addHours((int) config('payments.easypaisa.expiry_hours', 24))
                ->format('YmdHis'),
            'autoRedirect' => (string) config('payments.easypaisa.auto_redirect', '1'),
            'emailAddr' => $order->customer_email ?? '',
            'mobileNum' => $order->customer_phone,
            'paymentMethod' => '',
        ];

        $fields['merchantHashedReq'] = EasypaisaSignature::encryptRequest(
            $fields,
            (string) config('payments.easypaisa.hash_key'),
        );

        return [
            'gateway' => 'easypaisa',
            'action_url' => (string) config('payments.easypaisa.form_url'),
            'method' => 'POST',
            'reference' => $reference,
            'payment_id' => $payment->id,
            'fields' => $fields,
            'confirm_url' => (string) config('payments.easypaisa.confirm_url'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     reference: string,
     *     transaction_id: string|null,
     *     status: string,
     *     response_code: string|null,
     *     response_message: string|null,
     *     amount: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function parseCallback(array $payload): array
    {
        $reference = (string) ($payload['orderRefNum'] ?? $payload['orderRefNumber'] ?? '');

        if ($reference === '') {
            throw new BusinessException(
                'Easypaisa callback is missing order reference.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $status = (string) ($payload['status'] ?? $payload['transactionStatus'] ?? '');
        $responseCode = isset($payload['responseCode']) ? (string) $payload['responseCode'] : null;
        $amount = isset($payload['amount']) ? (string) $payload['amount'] : null;

        if (isset($payload['storeId'])) {
            $storeId = (string) config('payments.easypaisa.store_id');

            if ($storeId !== '' && (string) $payload['storeId'] !== $storeId) {
                throw new BusinessException(
                    'Easypaisa callback store ID mismatch.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        return [
            'reference' => $reference,
            'transaction_id' => isset($payload['transactionId']) ? (string) $payload['transactionId'] : null,
            'status' => $status,
            'response_code' => $responseCode,
            'response_message' => isset($payload['desc']) ? (string) $payload['desc'] : null,
            'amount' => $amount,
            'payload' => $payload,
        ];
    }

    public function isSuccessfulCallback(string $status, ?string $responseCode): bool
    {
        if (strcasecmp($status, 'Success') === 0) {
            return true;
        }

        return $responseCode === '0000';
    }

    public function assertConfigured(): void
    {
        foreach (['store_id', 'hash_key', 'postback_url'] as $key) {
            $value = config('payments.easypaisa.'.$key);

            if (! is_string($value) || $value === '') {
                throw new BusinessException(
                    'Easypaisa gateway is not configured.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }
    }

    private function postbackUrl(): string
    {
        return (string) config('payments.easypaisa.postback_url');
    }
}
