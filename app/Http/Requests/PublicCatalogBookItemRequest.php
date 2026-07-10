<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicCatalogBookItemRequest extends FormRequest
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
            'slot_id' => 'required|string|max:255',
            'customerName' => 'nullable|string|max:255',
            'customerPhone' => 'required|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'flow_token' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slot_id.required' => 'Please select an available time slot.',
            'customerPhone.required' => 'Phone is required.',
        ];
    }
}
