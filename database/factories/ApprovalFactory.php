<?php

namespace Database\Factories;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Models\Approval;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reimbursement_id' => Reimbursement::factory(),
            'approver_id' => User::factory(),
            'level' => ApprovalLevel::Manager,
            'action' => ApprovalAction::Approved,
            'notes' => fake()->optional()->sentence(),
            'acted_at' => now(),
        ];
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => ApprovalAction::Rejected,
            'notes' => fake()->sentence(),
        ]);
    }

    public function finance(): static
    {
        return $this->state(fn (array $attributes) => ['level' => ApprovalLevel::Finance]);
    }
}
