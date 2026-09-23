<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialLabel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialLabel>
 */
class SocialLabelFactory extends Factory
{
    protected $model = SocialLabel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
