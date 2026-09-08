<?php

namespace Database\Factories;

use App\Enums\CustomPlanRequestStatus;
use App\Models\CustomPlanRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomPlanRequest>
 */
class CustomPlanRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'requested_by' => User::factory(),
            'requested_max_stores' => fake()->numberBetween(8, 20),
            'requested_max_owners' => fake()->numberBetween(1, 5),
            'message' => fake()->sentence(),
            'status' => CustomPlanRequestStatus::Pending,
        ];
    }
}
