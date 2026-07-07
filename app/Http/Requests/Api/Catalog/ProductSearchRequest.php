<?php

namespace App\Http\Requests\Api\Catalog;

class ProductSearchRequest extends CatalogListRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
    }

    public function searchTerm(): string
    {
        return trim($this->validated('q'));
    }
}
