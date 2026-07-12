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
            'status' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'near_lat' => 'nullable|numeric|between:-90,90',
            'near_lng' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:500',
            'tag' => 'nullable|string|max:100',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|string|max:20',
            'bedrooms' => 'nullable|string|max:20',
            'bathrooms' => 'nullable|string|max:20',
            'property_type' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:50',
            'availability' => 'nullable|string|max:100',
            'duration' => 'nullable|string|max:100',
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
            'status',
            'location',
            'near_lat',
            'near_lng',
            'radius_km',
            'tag',
            'make',
            'model',
            'year',
            'bedrooms',
            'bathrooms',
            'property_type',
            'fuel_type',
            'availability',
            'duration',
            'min_price',
            'max_price',
            'sort',
            'page',
            'per_page',
        ]);
    }
}
