<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DealerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('DEMO-????-####'),
            'name' => 'Demo dealer '.fake()->company(),
        ];
    }
}
