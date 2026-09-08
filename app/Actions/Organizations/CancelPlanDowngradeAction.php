<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\Subscription;
use RuntimeException;

class CancelPlanDowngradeAction
{
    /**
     * Undo a scheduled downgrade — organization keeps its current plan.
     *
     * @throws RuntimeException
     */
    public function execute(Organization $organization): Subscription
    {
        $subscription = $organization->currentSubscription();

        if (! $subscription || $subscription->pending_plan_id === null) {
            throw new RuntimeException('Tidak ada downgrade yang dijadwalkan untuk dibatalkan.');
        }

        $subscription->update(['pending_plan_id' => null]);

        return $subscription->fresh();
    }
}
