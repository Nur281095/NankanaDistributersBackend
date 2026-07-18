<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\JazzCashSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class JazzCashGateway
{
    /**
     * @return array<string, mixed>
     */
    public function buildInitiationPayload(Order $order, Payment $payment, string $reference): array
    {
        $this->assertConfigured();

        $txnDateTime = now()->format('YmdHis');
        $expiryDateTime = now()
            ->addHours((int) config('payments.jazzcash.expiry_hours', 72))
            ->format('YmdHis');

        $fields = [
            'pp_Version' => (string) config('payments.jazzcash.version'),
            'pp_TxnType' => (string) config('payments.jazzcash.txn_type'),
            'pp_Language' => (string) config('payments.jazzcash.language'),
            'pp_MerchantID' => (string) config('payments.jazzcash.merchant_id'),
            'pp_SubMerchantID' => '',
            'pp_Password' => (string) config('payments.jazzcash.password'),
            'pp_BankID' => '',
            'pp_ProductID' => '',
            'pp_TxnRefNo' => $reference,
            'pp_Amount' => $this->formatAmount($order->grand_total),
            'pp_TxnCurrency' => (string) config('payments.jazzcash.currency'),
            'pp_TxnDateTime' => $txnDateTime,
            'pp_BillReference' => $order->order_number,
            'pp_Description' => 'Order '.$order->order_number,
            'pp_TxnExpiryDateTime' => $expiryDateTime,
            'pp_ReturnURL' => $this->returnUrl(),
            'ppmpf_1' => $order->customer_phone,
            'ppmpf_2' => '',
            'ppmpf_3' => '',
            'ppmpf_4' => '',
            'ppmpf_5' => '',
        ];

        $fields['pp_SecureHash'] = JazzCashSignature::generate(
            $fields,
            (string) config('payments.jazzcash.integrity_salt'),
        );

        $token = Str::random(64);

        Cache::put(
            'jazzcash_payment_form:'.$token,
            $fields,
            now()->addMinutes(15),
        );

        $publicFields = $fields;
        unset($publicFields['pp_Password']);

        return [
            'gateway' => 'jazzcash',
            'action_url' => (string) config('payments.jazzcash.form_url'),
            'redirect_url' => url('/payments/jazzcash/checkout/'.$token),
            'method' => 'GET',
            'reference' => $reference,
            'payment_id' => $payment->id,
            'fields' => $publicFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     reference: string,
     *     transaction_id: string|null,
     *     response_code: string,
     *     response_message: string|null,
     *     amount: string,
     *     payload: array<string, mixed>
     * }
     */
    public function parseCallback(array $payload): array
    {
        $this->assertConfigured();

        $providedHash = (string) ($payload['pp_SecureHash'] ?? '');

        if (! JazzCashSignature::verify(
            $payload,
            (string) config('payments.jazzcash.integrity_salt'),
            $providedHash,
        )) {
            throw new BusinessException(
                'Invalid JazzCash callback signature.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $reference = (string) ($payload['pp_TxnRefNo'] ?? '');
        $responseCode = (string) ($payload['pp_ResponseCode'] ?? '');

        if ($reference === '') {
            throw new BusinessException(
                'JazzCash callback is missing transaction reference.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return [
            'reference' => $reference,
            'transaction_id' => isset($payload['pp_RetreivalReferenceNo'])
                ? (string) $payload['pp_RetreivalReferenceNo']
                : null,
            'response_code' => $responseCode,
            'response_message' => isset($payload['pp_ResponseMessage'])
                ? (string) $payload['pp_ResponseMessage']
                : null,
            'amount' => $this->normalizeGatewayAmount((string) ($payload['pp_Amount'] ?? '0')),
            'payload' => $payload,
        ];
    }

    public function isSuccessfulResponse(string $responseCode): bool
    {
        return $responseCode === '000';
    }

    public function formatAmount(string|float $amount): string
    {
        return (string) ((int) round(((float) $amount) * 100));
    }

    public function normalizeGatewayAmount(string $amount): string
    {
        return number_format(((int) $amount) / 100, 2, '.', '');
    }

    public function assertConfigured(): void
    {
        foreach (['merchant_id', 'password', 'integrity_salt', 'return_url'] as $key) {
            $value = config('payments.jazzcash.'.$key);

            if (! is_string($value) || $value === '') {
                throw new BusinessException(
                    'JazzCash gateway is not configured.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }
    }

    private function returnUrl(): string
    {
        return (string) config('payments.jazzcash.return_url');
    }
}
