<?php

namespace Modules\Social\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialQueueSlotRequest extends FormRequest
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
            'weekday' => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weekday.required' => __('Choose a weekday for this queue slot.'),
            'time.required' => __('Choose a time for this queue slot.'),
            'time.date_format' => __('Use 24-hour time like 09:00.'),
        ];
    }
}
