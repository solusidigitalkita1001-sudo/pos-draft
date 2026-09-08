<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'code' => $name,
            'name' => ucfirst($name),
            'max_stores' => 1,
            'max_owners' => 1,
            'price_monthly' => 0,
            'price_yearly' => 0,
            'is_custom' => false,
            'is_active' => true,
            'sort_order' => 0,
            'features' => [],
        ];
    }

    /**
     * Indicate that the plan has negotiable (unlimited) quotas, like the
     * "custom" plan.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_stores' => null,
            'max_owners' => null,
            'price_monthly' => null,
            'price_yearly' => null,
            'is_custom' => true,
        ]);
    }
}
