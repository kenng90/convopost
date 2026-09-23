<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAnalyticsSnapshot;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialPostAnalyticsSnapshot>
 */
class SocialPostAnalyticsSnapshotFactory extends Factory
{
    protected $model = SocialPostAnalyticsSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'social_post_id' => SocialPost::factory(),
            'social_post_account_id' => null,
            'provider' => 'facebook',
            'provider_post_id' => (string) $this->faker->numerify('##########'),
            'impressions' => $this->faker->numberBetween(100, 5000),
            'reach' => $this->faker->numberBetween(50, 4000),
            'likes' => $this->faker->numberBetween(0, 200),
            'comments' => $this->faker->numberBetween(0, 50),
            'shares' => $this->faker->numberBetween(0, 30),
            'clicks' => $this->faker->numberBetween(0, 100),
            'engagement' => $this->faker->numberBetween(0, 300),
            'raw' => [],
            'synced_at' => now(),
        ];
    }
}
