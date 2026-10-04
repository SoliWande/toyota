<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DealerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'is_active' => true,
            'province' => null,
            'phone' => null,
            'address' => null,
            'code' => fake()->unique()->bothify('DEMO-????-####'),
            'name' => 'Demo dealer '.fake()->company(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
