<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostActivity;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialPostActivity>
 */
class SocialPostActivityFactory extends Factory
{
    protected $model = SocialPostActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'social_post_id' => SocialPost::factory(),
            'user_id' => null,
            'action' => 'created',
            'message' => null,
            'meta' => [],
        ];
    }
}
