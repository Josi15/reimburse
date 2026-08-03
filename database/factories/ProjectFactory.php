<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('####'),
            'name' => 'Proyek '.fake()->words(2, true),
            'description' => fake()->sentence(),
            'budget' => fake()->numberBetween(10, 500) * 1_000_000,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(60)->toDateString(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Proyek yang dipegang seorang Project Manager. */
    public function managedBy(User $user): static
    {
        return $this->state(fn () => ['manager_id' => $user->id]);
    }
}
