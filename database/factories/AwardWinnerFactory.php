<?php

namespace Database\Factories;

use App\Enums\AwardWinnerType;
use App\Models\Award;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AwardWinnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'award_id' => Award::factory(),
            'winner_type' => AwardWinnerType::Sales,
            'sales_id' => User::factory()->active(),
            'dealer_id' => null,
            'rank' => 1,
            'score' => fake()->numberBetween(1, 100),
            'winner_name_snapshot' => fn (array $attributes) => User::findOrFail($attributes['sales_id'])->name,
            'sales_dealer_id_snapshot' => fn (array $attributes) => User::findOrFail($attributes['sales_id'])->dealer_id,
            'dealer_name_snapshot' => fn (array $attributes) => Dealer::findOrFail($attributes['sales_dealer_id_snapshot'])->name,
            'dealer_code_snapshot' => fn (array $attributes) => Dealer::findOrFail($attributes['sales_dealer_id_snapshot'])->code,
        ];
    }

    public function dealer(): static
    {
        return $this->state(fn () => [
            'winner_type' => AwardWinnerType::Dealer,
            'sales_id' => null,
            'dealer_id' => Dealer::factory(),
            'sales_dealer_id_snapshot' => null,
            'winner_name_snapshot' => fn (array $attributes) => Dealer::findOrFail($attributes['dealer_id'])->name,
            'dealer_name_snapshot' => fn (array $attributes) => Dealer::findOrFail($attributes['dealer_id'])->name,
            'dealer_code_snapshot' => fn (array $attributes) => Dealer::findOrFail($attributes['dealer_id'])->code,
        ]);
    }
}
