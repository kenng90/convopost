<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialTemplate;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialTemplate>
 */
class SocialTemplateFactory extends Factory
{
    protected $model = SocialTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'user_id' => null,
            'name' => $this->faker->sentence(3),
            'content' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['promo', 'launch', 'engagement', null]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
