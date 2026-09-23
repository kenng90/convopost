<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialComment;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialComment>
 */
class SocialCommentFactory extends Factory
{
    protected $model = SocialComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'social_post_id' => null,
            'social_post_account_id' => null,
            'social_account_id' => null,
            'contact_id' => null,
            'provider' => 'facebook',
            'provider_comment_id' => (string) $this->faker->unique()->numerify('##############'),
            'provider_post_id' => (string) $this->faker->numerify('##########_##########'),
            'author_name' => $this->faker->name(),
            'author_username' => $this->faker->userName(),
            'author_external_id' => (string) $this->faker->numerify('##########'),
            'body' => $this->faker->sentence(),
            'commented_at' => now()->subMinutes($this->faker->numberBetween(1, 120)),
            'raw' => [],
        ];
    }

    public function forProvider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }
}
