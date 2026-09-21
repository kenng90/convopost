<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostVersion;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialPostVersion>
 */
class SocialPostVersionFactory extends Factory
{
    protected $model = SocialPostVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'provider' => 'default',
            'content' => $this->faker->paragraph(),
            'media_ids' => [],
            'first_comment' => null,
            'provider_payload' => [],
        ];
    }

    public function forProvider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }
}
