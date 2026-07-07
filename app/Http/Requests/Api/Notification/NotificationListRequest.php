<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class NotificationListRequest extends FormRequest
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
            'is_read' => ['sometimes', 'boolean'],
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

    public function isReadFilter(): ?bool
    {
        if (! array_key_exists('is_read', $this->validated())) {
            return null;
        }

        return (bool) $this->validated('is_read');
    }
}
