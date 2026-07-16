<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bank>
 */
class BankFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Bank';

        return [
            'name' => $name,
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'swift_code' => Str::upper(fake()->lexify('????IDJA')),
            'is_active' => true,
        ];
    }
}
