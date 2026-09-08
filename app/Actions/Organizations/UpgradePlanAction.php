<?php

namespace App\Actions\Organizations;

use App\Exceptions\StoreQuotaExceededException;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class UpgradePlanAction
{
    /**
     * Switch an organization's current subscription to a different plan.
     *
     * This is a self-service plan SWITCH only (no payment processing —
     * that lands in a later sprint alongside the payment gateway). It
     * refuses to switch to a plan whose store quota is smaller than the
     * organization's current store count, so an org can never end up
     * over quota because of its own upgrade/downgrade action.
     *
     * @throws StoreQuotaExceededException when the target plan's quota
     *         is smaller than the organization's current store count.
     */
    public function execute(Organization $organization, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($organization, $plan) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $currentStoreCount = $organization->teams()->count();

            // Custom plan has no fixed quota — the new subscription starts
            // with an override equal to what they already have, so the
            // account owner (or admin, for real "custom" deals) can raise
            // it afterwards without ever silently dropping below it.
            $maxStoresOverride = $plan->is_custom ? $currentStoreCount : null;

            if (! $plan->is_custom && $plan->max_stores !== null && $currentStoreCount > $plan->max_stores) {
                throw new StoreQuotaExceededException($organization, $plan->max_stores);
            }

            $subscription = $organization->currentSubscription();

            if (! $subscription) {
                throw new \RuntimeException("Organization #{$organization->id} has no subscription to upgrade.");
            }

            $subscription->update([
                'plan_id' => $plan->id,
                'max_stores_override' => $maxStoresOverride,
            ]);

            return $subscription->fresh('plan');
        });
    }
}
