<?php

namespace Database\Factories;

use App\Models\CompanyAccountDeposit;
use App\Models\CompanyBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAccountDeposit>
 */
class CompanyAccountDepositFactory extends Factory
{
    protected $model = CompanyAccountDeposit::class;

    public function definition(): array
    {
        return [
            'company_bank_account_id' => CompanyBankAccount::factory(),
            'amount' => fake()->numberBetween(5, 100) * 1_000_000,
            'deposited_at' => now()->toDateString(),
            'note' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
