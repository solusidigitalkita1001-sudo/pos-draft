<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $organization = Organization::factory()->create([
                'name' => $user->name."'s Organization",
            ]);

            $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner,
            ]);

            // Generous max_stores (10) so existing/unrelated tests that
            // create a handful of teams for one factory user don't
            // accidentally trip the store quota. Tests that specifically
            // exercise quota behaviour should build their own
            // Organization/Plan/Subscription instead of relying on this.
            $plan = Plan::factory()->create([
                'max_stores' => 10,
                'max_owners' => 5,
            ]);

            \App\Models\Subscription::factory()->active()->create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
            ]);

            $user->switchOrganization($organization);

            $team = Team::factory()->personal()->create([
                'organization_id' => $organization->id,
                'name' => $user->name."'s Team",
            ]);

            $team->members()->attach($user, [
                'role' => TeamRole::Owner->value,
            ]);

            $user->switchTeam($team);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
