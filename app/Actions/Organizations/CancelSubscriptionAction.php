<?php

namespace App\Actions\Organizations;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelSubscriptionAction
{
    /**
     * Voluntarily cancel an organization's subscription.
     *
     * - Trial: no paid period to "run out", so cancellation is
     *   immediate (status -> Canceled right away).
     * - Active / PastDue: cancellation takes effect at the END of the
     *   current paid period — access continues normally until then
     *   (`canceled_at` is set as a marker; ProcessSubscriptionLifecycleAction
     *   finalizes the actual status change once current_period_end
     *   passes). This matches how most subscription products behave:
     *   you don't lose access to something you already paid for.
     *
     * @throws RuntimeException if the subscription is already
     *         suspended/canceled, or there's no subscription at all.
     */
    public function execute(Organization $organization): Subscription
    {
        return DB::transaction(function () use ($organization) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $subscription = $organization->currentSubscription();

            if (! $subscription) {
                throw new RuntimeException('Organization ini tidak punya subscription aktif.');
            }

            if (! $subscription->status->isUsable()) {
                throw new RuntimeException('Subscription ini sudah tidak aktif.');
            }

            if ($subscription->status === SubscriptionStatus::Trial) {
                $subscription->update([
                    'status' => SubscriptionStatus::Canceled,
                    'canceled_at' => now(),
                    'pending_plan_id' => null,
                ]);

                return $subscription->fresh();
            }

            // Cancellation supersedes a scheduled downgrade — no point
            // switching plans right before the subscription ends anyway.
            $subscription->update(['canceled_at' => now(), 'pending_plan_id' => null]);

            return $subscription->fresh();
        });
    }
}
