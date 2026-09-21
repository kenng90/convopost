<?php

namespace Modules\Social\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Social\Models\SocialPost;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Social\Models\SocialPost>
 */
class SocialPostFactory extends Factory
{
    protected $model = SocialPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'user_id' => null,
            'status' => 'draft',
            'approval_status' => 'none',
            'scheduled_at' => null,
            'published_at' => null,
            'label_ids' => [],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'scheduled_at' => null,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'scheduled_at' => now()->subHour(),
            'published_at' => now()->subMinutes(30),
        ]);
    }

    public function withDefaultVersion(?string $content = null): static
    {
        return $this->afterCreating(function (SocialPost $post) use ($content) {
            $post->versions()->create([
                'provider' => 'default',
                'content' => $content ?? fake()->paragraph(),
                'media_ids' => [],
            ]);
        });
    }
}
