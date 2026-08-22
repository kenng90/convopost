<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallVerticalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vertical' => ['required', 'string', Rule::in(array_keys(config('vertical-golive.packs', [])))],
            'install_playbook' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vertical.required' => __('Choose a vertical pack to go live.'),
            'vertical.in' => __('That vertical pack is not available.'),
        ];
    }
}
