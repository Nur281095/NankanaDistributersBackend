<?php

namespace App\Http\Requests\Api\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderListRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status_group' => ['sometimes', 'string', Rule::in(['current', 'delivered', 'cancelled'])],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function statusGroup(): ?string
    {
        $value = $this->validated('status_group');

        return is_string($value) ? $value : null;
    }
}
