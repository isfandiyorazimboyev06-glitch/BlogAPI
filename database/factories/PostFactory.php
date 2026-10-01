<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'        => fake()->sentence(),
            'content'      => fake()->paragraphs(3, true),
            'status'       => 'draft',
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the post is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the post is scheduled.
     */
    public function scheduled(?string $time = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'scheduled',
            'published_at' => $time ?? now()->addDay(),
        ]);
    }

    /**
     * Indicate that the post is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'draft',
            'published_at' => null,
        ]);
    }
}
