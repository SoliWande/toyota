<?php

namespace Database\Factories;

use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModerationReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_id' => null,
            'customer_submission_id' => CustomerSubmission::factory()->approved(),
            'reviewed_by' => fn (array $attributes) => CustomerSubmission::findOrFail($attributes['customer_submission_id'])->reviewed_by,
            'from_status' => 'pending',
            'to_status' => 'approved',
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'admin_note' => null,
        ];
    }

    public function sales(): static
    {
        return $this->state(fn () => [
            'sales_id' => User::factory()->active(),
            'customer_submission_id' => null,
            'reviewed_by' => User::factory()->admin(),
            'to_status' => 'active',
        ]);
    }
}
