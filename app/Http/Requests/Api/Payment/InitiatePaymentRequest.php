<?php

namespace App\Http\Requests\Api\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_method' => [
                'required',
                Rule::enum(PaymentMethod::class),
                Rule::in([
                    PaymentMethod::Jazzcash->value,
                    PaymentMethod::Easypaisa->value,
                ]),
            ],
        ];
    }

    public function orderId(): int
    {
        return (int) $this->validated('order_id');
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('payment_method'));
    }
}
