<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganizationAction
{
    /**
     * Number of trial days granted to a brand new organization before
     * it needs an active (paid) subscription.
     */
    private const TRIAL_DAYS = 14;

    /**
     * Create a new organization for the given user, with the given plan
     * started as a trial subscription. Does not create a Team (toko) —
     * that stays a separate step so the onboarding flow can let the user
     * name their first store.
     */
    public function execute(User $user, string $name, Plan $plan): Organization
    {
        return DB::transaction(function () use ($user, $name, $plan) {
            /** @var Organization $organization */
            $organization = Organization::create([
                'name' => $name,
            ]);

            $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner,
            ]);

            $trialEndsAt = now()->addDays(self::TRIAL_DAYS);

            Subscription::create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
            ]);

            $user->switchOrganization($organization);

            return $organization->load('memberships', 'subscriptions.plan');
        });
    }
}
