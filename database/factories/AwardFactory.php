<?php

namespace Database\Factories;

use App\Enums\AwardPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AwardFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'title' => 'Monthly community awards',
            'period_type' => AwardPeriod::Monthly,
            'period_start' => $start,
            'period_end' => $start->copy()->addMonth(),
        ];
    }

    public function weekly(): static
    {
        return $this->state(function () {
            $start = now()->startOfWeek(); // Fixture only; business calendar remains unconfirmed.

            return [
                'title' => 'Weekly community awards',
                'period_type' => AwardPeriod::Weekly,
                'period_start' => $start,
                'period_end' => $start->copy()->addWeek(),
            ];
        });
    }
}
