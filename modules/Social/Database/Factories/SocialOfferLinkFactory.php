<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialOfferLink>
 */
class SocialOfferLinkFactory extends Factory
{
    protected $model = SocialOfferLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'social_post_id' => SocialPost::factory(),
            'offer_type' => 'url',
            'target_id' => null,
            'url' => $this->faker->url(),
            'tracking_token' => Str::random(40),
            'click_count' => 0,
        ];
    }

    public function forProduct(int $productId): static
    {
        return $this->state(fn () => [
            'offer_type' => 'product',
            'target_id' => $productId,
        ]);
    }
}
