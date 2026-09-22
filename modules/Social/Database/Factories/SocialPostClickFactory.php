<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPostClick;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialPostClick>
 */
class SocialPostClickFactory extends Factory
{
    protected $model = SocialPostClick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'social_offer_link_id' => SocialOfferLink::factory(),
            'social_post_id' => null,
            'ip_hash' => hash('sha256', $this->faker->ipv4()),
            'user_agent' => $this->faker->userAgent(),
            'referer' => $this->faker->url(),
            'clicked_at' => now(),
        ];
    }
}
