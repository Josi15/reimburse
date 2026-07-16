<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Engineering', 'Marketing', 'Sales', 'Operations',
            'Human Resources', 'Legal', 'Procurement', 'Customer Support',
        ]);

        return [
            'name' => $name,
            'code' => Str::upper(Str::substr(Str::slug($name, ''), 0, 6)).fake()->unique()->numberBetween(10, 99),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
