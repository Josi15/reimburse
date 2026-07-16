<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Travel', 'Meals', 'Accommodation', 'Office Supplies',
            'Transportation', 'Training', 'Medical', 'Communication',
        ]);

        return [
            'name' => $name,
            'code' => Str::upper(Str::substr($name, 0, 4)).fake()->unique()->numberBetween(10, 99),
            'description' => fake()->sentence(),
            // Plafon dalam rupiah penuh; kadang tanpa batas (null).
            'max_amount' => fake()->optional(0.7)->randomElement([500_000, 1_000_000, 2_500_000, 5_000_000]),
            'is_active' => true,
        ];
    }
}
