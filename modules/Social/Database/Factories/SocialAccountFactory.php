<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialAccount;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = $this->faker->randomElement(['facebook', 'instagram', 'linkedin', 'tiktok', 'youtube']);

        return [
            'company_id' => \App\Models\Company::factory(),
            'provider' => $provider,
            'external_id' => (string) $this->faker->unique()->numerify('##########'),
            'name' => $this->faker->company(),
            'username' => $this->faker->userName(),
            'avatar' => $this->faker->imageUrl(128, 128),
            'access_token' => encrypt('test-access-token'),
            'refresh_token' => encrypt('test-refresh-token'),
            'token_expires_at' => now()->addDays(60),
            'scopes' => ['pages_manage_posts', 'pages_read_engagement'],
            'meta' => ['page_id' => $this->faker->numerify('##########')],
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subDay(),
        ]);
    }

    public function forProvider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }
}
