<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestPlanDowngradeAction
{
    /**
     * Schedule a downgrade to a smaller/cheaper plan. Does NOT switch
     * the plan immediately — organization already paid for the current
     * period at the current (bigger) plan, so they keep its quota until
     * `current_period_end`. The actual switch happens in
     * ProcessSubscriptionLifecycleAction once the period ends (see
     * finalizePendingDowngrades()).
     *
     * Refuses:
     * - targets that aren't actually a downgrade (use checkout for
     *   upgrades — those need payment)
     * - the "custom" plan (that always goes through the manual request
     *   flow, see RequestCustomPlanAction)
     * - targets whose quota can't fit the CURRENT store count — this is
     *   only an early warning; the authoritative check happens again
     *   when the downgrade is finalized, since the store count can
     *   still change between now and period end.
     *
     * @throws RuntimeException
     */
    public function execute(Organization $organization, Plan $targetPlan): Subscription
    {
        return DB::transaction(function () use ($organization, $targetPlan) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $subscription = $organization->currentSubscription();

            if (! $subscription) {
                throw new RuntimeException('Organization ini tidak punya subscription aktif.');
            }

            if ($targetPlan->is_custom) {
                throw new RuntimeException('Plan custom tidak bisa dipilih lewat downgrade otomatis — gunakan form request custom plan.');
            }

            if ($targetPlan->sort_order >= $subscription->plan->sort_order) {
                throw new RuntimeException('Paket tujuan bukan downgrade dari paket saat ini — gunakan alur checkout untuk upgrade.');
            }

            $currentStoreCount = $organization->teams()->count();

            if ($targetPlan->max_stores !== null && $currentStoreCount > $targetPlan->max_stores) {
                throw new RuntimeException('Jumlah toko Anda saat ini melebihi kuota paket tujuan. Kurangi jumlah toko terlebih dahulu.');
            }

            $subscription->update([
                'pending_plan_id' => $targetPlan->id,
                // Downgrade supersedes a pending cancellation — requesting
                // a downgrade means they want to keep using the product,
                // just on a smaller plan.
                'canceled_at' => null,
            ]);

            return $subscription->fresh();
        });
    }
}
