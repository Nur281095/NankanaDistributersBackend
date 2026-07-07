<?php

namespace App\Http\Requests\Api\Checkout;

use App\Enums\PaymentMethod;
use App\Enums\UserStatus;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Rules\PakistanPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class CheckoutSummaryRequest extends FormRequest
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
        return $this->isAuthenticatedCheckout()
            ? $this->authenticatedRules()
            : $this->guestRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->bearerToken() !== null && $this->authenticatedUser() === null) {
                $validator->errors()->add('token', 'Invalid or expired token.');
            }

            $user = $this->authenticatedUser();

            if ($user !== null && $user->status !== UserStatus::Active) {
                $validator->errors()->add('account', 'Your account is not active.');
            }

            if ($user !== null && $this->filled('address_id')) {
                $address = CustomerAddress::query()->find($this->integer('address_id'));

                if ($address === null || $address->user_id !== $user->id) {
                    $validator->errors()->add('address_id', 'The selected address is invalid.');
                }
            }
        });
    }

    public function checkoutUser(): ?User
    {
        return $this->authenticatedUser();
    }

    protected function isAuthenticatedCheckout(): bool
    {
        return $this->bearerToken() !== null;
    }

    protected function authenticatedUser(): ?User
    {
        if ($this->bearerToken() === null) {
            return null;
        }

        $token = PersonalAccessToken::findToken($this->bearerToken());
        $user = $token?->tokenable;

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function authenticatedRules(): array
    {
        return array_merge($this->sharedRules(), [
            'address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'name' => ['required_without:address_id', 'string', 'max:255'],
            'phone' => ['required_without:address_id', 'string', 'max:20', new PakistanPhoneNumber],
            'address' => ['required_without:address_id', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'items' => ['prohibited'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function guestRules(): array
    {
        return array_merge($this->sharedRules(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new PakistanPhoneNumber],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'address_id' => ['prohibited'],
        ]);
    }
}
