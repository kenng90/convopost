<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialHashtagGroup;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialHashtagGroup>
 */
class SocialHashtagGroupFactory extends Factory
{
    protected $model = SocialHashtagGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'name' => $this->faker->words(2, true),
            'tags' => ['kenya', 'mpesa', 'shop'],
        ];
    }
}
