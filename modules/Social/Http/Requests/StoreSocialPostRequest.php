<?php

namespace Modules\Social\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
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
        return self::composerRules(
            (string) $this->input('status', 'draft'),
            (string) $this->input('offer_type', 'none'),
        );
    }

    /**
     * Shared rules for Livewire composer and HTTP form posts.
     *
     * @return array<string, mixed>
     */
    public static function composerRules(string $status = 'draft', string $offerType = 'none'): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => ['integer'],
            'versions' => ['nullable', 'array'],
            'versions.facebook' => ['nullable', 'string', 'max:5000'],
            'versions.instagram' => ['nullable', 'string', 'max:5000'],
            'versions.linkedin' => ['nullable', 'string', 'max:5000'],
            'versions.tiktok' => ['nullable', 'string', 'max:5000'],
            'versions.youtube' => ['nullable', 'string', 'max:5000'],
            'versions.threads' => ['nullable', 'string', 'max:500'],
            'versions.pinterest' => ['nullable', 'string', 'max:800'],
            'status' => ['required', Rule::in(['draft', 'scheduled'])],
            'scheduled_at' => [
                Rule::requiredIf($status === 'scheduled'),
                'nullable',
                'date',
                'after:now',
            ],
            'offer_type' => ['required', Rule::in(['none', 'url', 'catalog', 'product'])],
            'offer_url' => [
                Rule::requiredIf($offerType === 'url'),
                'nullable',
                'url',
                'max:2048',
            ],
            'offer_target_id' => [
                Rule::requiredIf(in_array($offerType, ['catalog', 'product'], true)),
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => __('Write a caption for your post.'),
            'account_ids.required' => __('Select at least one social account.'),
            'account_ids.min' => __('Select at least one social account.'),
            'scheduled_at.required' => __('Choose a schedule time to publish later.'),
            'scheduled_at.after' => __('Schedule time must be in the future.'),
            'offer_url.required' => __('Enter an offer URL.'),
            'offer_target_id.required' => __('Choose a catalog or product for the offer.'),
        ];
    }

    /**
     * Livewire field map for the same validation rules.
     *
     * @return array<string, mixed>
     */
    public static function livewireRules(string $status = 'draft', string $offerType = 'none'): array
    {
        $rules = self::composerRules($status, $offerType);

        return [
            'content' => $rules['content'],
            'selectedAccountIds' => $rules['account_ids'],
            'selectedAccountIds.*' => $rules['account_ids.*'],
            'selectedMediaIds' => $rules['media_ids'],
            'selectedMediaIds.*' => $rules['media_ids.*'],
            'networkVersions.facebook' => $rules['versions.facebook'],
            'networkVersions.instagram' => $rules['versions.instagram'],
            'networkVersions.linkedin' => $rules['versions.linkedin'],
            'networkVersions.tiktok' => $rules['versions.tiktok'],
            'networkVersions.youtube' => $rules['versions.youtube'],
            'networkVersions.threads' => $rules['versions.threads'],
            'networkVersions.pinterest' => $rules['versions.pinterest'],
            'scheduledAt' => $rules['scheduled_at'],
            'offerType' => $rules['offer_type'],
            'offerUrl' => $rules['offer_url'],
            'offerTargetId' => $rules['offer_target_id'],
        ];
    }
}
