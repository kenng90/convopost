<?php

namespace Modules\Social\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialHashtagGroupRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'tags' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Group name is required.'),
            'tags.required' => __('Add at least one hashtag.'),
        ];
    }
}
