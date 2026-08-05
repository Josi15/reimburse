<?php

namespace Database\Factories;

use App\Enums\ClaimType;
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
            'claim_type' => ClaimType::Expense,
            'details' => null,
            'user_id' => User::factory(),
            // Departemen klaim SELALU mengikuti departemen pengajunya (lihat
            // ReimbursementService::resolveDepartment). Fallback dipakai bila
            // pengajunya belum punya departemen.
            'department_id' => function (array $attributes) {
                $userId = $attributes['user_id'] ?? null;

                return (is_int($userId) ? User::find($userId)?->department_id : null)
                    ?? Department::factory();
            },
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

    /** Pengajuan pengadaan barang (nominal = jumlah × harga satuan). */
    public function goods(): static
    {
        return $this->state(function () {
            $quantity = fake()->numberBetween(1, 5);
            $unitPrice = fake()->numberBetween(500_000, 20_000_000);

            return [
                'claim_type' => ClaimType::Goods,
                'amount' => $quantity * $unitPrice,
                'details' => [
                    'item_name' => fake()->randomElement(['Server Dell R650', 'Laptop ThinkPad T14', 'Switch Cisco 24-port']),
                    'specification' => fake()->sentence(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'vendor' => fake()->company(),
                    'urgency' => fake()->randomElement(['normal', 'urgent', 'critical']),
                ],
            ];
        });
    }

    /** Pengajuan lembur (nominal = total jam × upah per jam). */
    public function overtime(): static
    {
        return $this->state(function () {
            $hours = fake()->randomElement([2, 3, 4.5, 6]);
            $rate = fake()->numberBetween(25_000, 75_000);

            return [
                'claim_type' => ClaimType::Overtime,
                'amount' => (int) round($hours * $rate),
                'details' => [
                    'overtime_date' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                    'start_time' => '18:00',
                    'end_time' => '22:00',
                    'hours' => $hours,
                    'hourly_rate' => $rate,
                    'work_description' => fake()->sentence(),
                ],
            ];
        });
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
