<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_id' => User::factory()->active(),
            'customer_name' => fake()->name(),
            'facebook_url' => 'https://www.facebook.com/profile.php?id='.fake()->unique()->numerify('10#############'),
            'phone' => null,
            'notes' => null,
            'submitted_at' => now(),
            'status' => SubmissionStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Demo: membership could not be verified.',
        ]);
    }
}
