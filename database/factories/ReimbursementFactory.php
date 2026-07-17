<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Department;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reimbursement>
 */
class ReimbursementFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->numberBetween(50_000, 5_000_000);

        return [
            'reimbursement_number' => 'RMB-'.date('Y').'-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory(),
            'department_id' => Department::factory(),
            'category_id' => Category::factory(),
            'bank_account_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'reason' => fake()->sentence(),
            'amount' => $amount,
            'currency' => 'IDR',
            'status' => 'draft',
            'expense_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function managerApproved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'manager_approved']);
    }

    public function financeApproved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'finance_approved']);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'completed_at' => now(),
        ]);
    }
}
