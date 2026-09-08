<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResumeSubscriptionAction
{
    /**
     * Undo a pending cancellation — only possible while the current
     * period hasn't ended yet (i.e. before ProcessSubscriptionLifecycleAction
     * has finalized it into an actual Canceled status).
     *
     * @throws RuntimeException
     */
    public function execute(Organization $organization): Subscription
    {
        return DB::transaction(function () use ($organization) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $subscription = $organization->currentSubscription();

            if (! $subscription || $subscription->canceled_at === null) {
                throw new RuntimeException('Tidak ada pembatalan yang bisa dibatalkan.');
            }

            if (! $subscription->status->isUsable()) {
                throw new RuntimeException('Subscription ini sudah dihentikan dan tidak bisa diaktifkan ulang lewat sini — silakan upgrade paket.');
            }

            $subscription->update(['canceled_at' => null]);

            return $subscription->fresh();
        });
    }
}
