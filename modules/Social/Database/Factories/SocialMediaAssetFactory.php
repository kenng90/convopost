<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialMediaAsset;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialMediaAsset>
 */
class SocialMediaAssetFactory extends Factory
{
    protected $model = SocialMediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mime = $this->faker->randomElement(['image/jpeg', 'image/png', 'video/mp4']);
        $isVideo = str_starts_with($mime, 'video/');

        return [
            'company_id' => \App\Models\Company::factory(),
            'uploaded_by' => null,
            'disk' => 'public',
            'path' => 'social/'.fake()->uuid().($isVideo ? '.mp4' : '.jpg'),
            'original_name' => $this->faker->word().($isVideo ? '.mp4' : '.jpg'),
            'mime' => $mime,
            'size' => $this->faker->numberBetween(10_000, 5_000_000),
            'width' => $isVideo ? 1080 : $this->faker->numberBetween(640, 1920),
            'height' => $isVideo ? 1920 : $this->faker->numberBetween(480, 1080),
            'duration' => $isVideo ? $this->faker->numberBetween(5, 60) : null,
            'meta' => [],
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'mime' => 'image/jpeg',
            'path' => 'social/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'duration' => null,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'mime' => 'video/mp4',
            'path' => 'social/'.fake()->uuid().'.mp4',
            'original_name' => 'clip.mp4',
            'duration' => 15,
            'width' => 1080,
            'height' => 1920,
        ]);
    }
}
