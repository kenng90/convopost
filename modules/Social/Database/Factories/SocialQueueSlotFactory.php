<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialQueueSlot;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialQueueSlot>
 */
class SocialQueueSlotFactory extends Factory
{
    protected $model = SocialQueueSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'weekday' => $this->faker->numberBetween(1, 5),
            'time' => $this->faker->randomElement(['09:00', '12:00', '17:00']),
            'timezone' => config('app.timezone', 'UTC'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
