<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issued_on' => now()->toDateString(),
            'buyer_name' => fake()->name(),
            'phone' => '555 12 34 56',
            'lines' => [
                ['name' => 'JBL Charge 6', 'quantity' => 2, 'unit_price' => 60],
            ],
            'discount' => 0,
            'shipping' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['paid_at' => now()]);
    }
}
