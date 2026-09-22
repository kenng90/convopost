<?php

namespace Modules\Social\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialMediaAssetRequest extends FormRequest
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
        $maxKilobytes = (int) config('social.media.max_kilobytes', 51200);
        $mimes = implode(',', (array) config('social.media.allowed_mimes', [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm',
        ]));

        return [
            'file' => [
                'required',
                'file',
                'mimes:'.$mimes,
                'max:'.$maxKilobytes,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = round(((int) config('social.media.max_kilobytes', 51200)) / 1024, 1);

        return [
            'file.required' => __('Please choose an image or video to upload.'),
            'file.mimes' => __('Only JPEG, PNG, GIF, WebP, MP4, MOV, or WebM files are allowed.'),
            'file.max' => __('The file may not be greater than :max MB.', ['max' => $maxMb]),
        ];
    }
}
