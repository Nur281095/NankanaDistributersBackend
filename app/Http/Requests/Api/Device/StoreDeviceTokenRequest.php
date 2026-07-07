<?php

namespace App\Http\Requests\Api\Device;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceTokenRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
        ];
    }

    public function deviceToken(): string
    {
        return trim($this->validated('token'));
    }

    public function platform(): DevicePlatform
    {
        return DevicePlatform::from($this->validated('platform'));
    }
}
