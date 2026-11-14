<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AbandonedCartFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 50, 2000);

        return [
            'token' => (string) Str::uuid(),
            'name' => fake()->name(),
            'phone' => '5'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'items' => ['1' => 1],
            'lines' => [['id' => 1, 'name' => 'JBL Charge 6', 'options' => [], 'price' => $price, 'quantity' => 1]],
            'subtotal' => $price,
            'locale' => 'ka',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn () => ['contacted_at' => now()]);
    }
}
