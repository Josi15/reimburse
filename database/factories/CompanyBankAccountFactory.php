<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyBankAccount>
 */
class CompanyBankAccountFactory extends Factory
{
    protected $model = CompanyBankAccount::class;

    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'label' => fake()->randomElement(['Kas Operasional', 'Payroll', 'Kas Proyek']),
            'account_number' => fake()->numerify('##########'),
            'account_holder_name' => 'PT '.fake()->company(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
