<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicCatalogBrowseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'stock' => 'nullable|string|in:In Stock,Out of Stock,Low Stock',
            'tag' => 'nullable|string|max:100',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'sort' => 'nullable|string|in:default,price_asc,price_desc,title_asc,title_desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:12|max:48',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->only([
            'q',
            'category',
            'stock',
            'tag',
            'min_price',
            'max_price',
            'sort',
            'page',
            'per_page',
        ]);
    }
}
