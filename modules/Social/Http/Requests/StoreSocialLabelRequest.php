<?php

namespace Modules\Social\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->currentCompany()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('social_labels', 'name')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Label name is required.'),
            'name.unique' => __('That label already exists.'),
            'color.regex' => __('Color must be a hex value like #0E8A7A.'),
        ];
    }
}
