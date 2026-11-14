<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CampaignFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($title),
            'title' => ['ka' => $title, 'en' => $title],
            'text' => ['ka' => fake()->sentence(), 'en' => fake()->sentence()],
            'badge' => ['ka' => '−20%', 'en' => '−20%'],
            'theme' => 'navy',
            'position' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => now()->addWeek(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subWeeks(2), 'ends_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
