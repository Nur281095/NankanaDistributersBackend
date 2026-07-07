<?php

namespace App\Http\Requests\Api\Auth;

use App\Rules\PakistanPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20', new PakistanPhoneNumber],
            'password' => ['required', 'string'],
        ];
    }
}
