<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 * payment_number diisi otomatis oleh PaymentObserver bila dikosongkan.
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reimbursement_id' => Reimbursement::factory(),
            'bank_account_id' => BankAccount::factory(),
            'processed_by' => User::factory(),
            'amount' => fake()->numberBetween(50_000, 5_000_000),
            'currency' => 'IDR',
            'method' => PaymentMethod::BankTransfer,
            'status' => PaymentStatus::Pending,
            'reference_number' => fake()->optional()->numerify('TRX#########'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'reference_number' => fake()->numerify('TRX#########'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Failed]);
    }
}
