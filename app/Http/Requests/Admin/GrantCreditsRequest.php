<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GrantCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'messaging_credits' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'ai_credits' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'messaging_expires_at' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required' => __('Please select an organization.'),
            'messaging_expires_at.after' => __('Messaging credit expiry must be a future date.'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $messaging = (float) $this->input('messaging_credits', 0);
            $ai = (int) $this->input('ai_credits', 0);

            if ($messaging <= 0 && $ai <= 0) {
                $validator->errors()->add(
                    'messaging_credits',
                    __('Enter messaging credits, AI credits, or both.')
                );
            }
        });
    }
}
