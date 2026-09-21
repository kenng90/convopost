<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAccount;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialPostAccount>
 */
class SocialPostAccountFactory extends Factory
{
    protected $model = SocialPostAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'social_account_id' => SocialAccount::factory(),
            'provider_post_id' => null,
            'status' => 'pending',
            'error' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'provider_post_id' => (string) fake()->numerify('##########'),
            'published_at' => now(),
            'error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error' => 'Publish failed',
            'published_at' => null,
        ]);
    }
}
