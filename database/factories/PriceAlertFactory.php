<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => fn () => Product::query()->inRandomOrder()->value('id'),
            'contact' => fake()->unique()->safeEmail(),
            'channel' => 'mail',
            'price' => fake()->randomFloat(2, 50, 3000),
            'locale' => 'ka',
        ];
    }

    public function sms(): static
    {
        return $this->state(fn () => [
            'contact' => '5'.fake()->numerify('########'),
            'channel' => 'sms',
        ]);
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes) => [
            'notified_at' => now(),
            'notified_price' => $attributes['price'] - 10,
        ]);
    }
}
