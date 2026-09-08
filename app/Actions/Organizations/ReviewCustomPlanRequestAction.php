<?php

namespace App\Actions\Organizations;

use App\Enums\CustomPlanRequestStatus;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\CustomPlanRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\Organizations\CustomPlanRequestReviewedNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewCustomPlanRequestAction
{
    /**
     * Approve a custom plan request: switches the organization's
     * subscription to the shared "custom" Plan row, with per-organization
     * quota overrides set to whatever the admin approved (which may
     * differ from what was originally requested).
     */
    public function approve(
        CustomPlanRequest $request,
        User $reviewer,
        ?int $approvedMaxStores,
        ?int $approvedMaxOwners,
    ): CustomPlanRequest {
        $request = DB::transaction(function () use ($request, $reviewer, $approvedMaxStores, $approvedMaxOwners) {
            $organization = Organization::whereKey($request->organization_id)->lockForUpdate()->firstOrFail();

            $customPlan = Plan::findByCode(PlanCode::Custom);

            if (! $customPlan) {
                throw new RuntimeException('Plan "custom" belum ada — jalankan PlanSeeder terlebih dahulu.');
            }

            $currentStoreCount = $organization->teams()->count();

            if ($approvedMaxStores !== null && $currentStoreCount > $approvedMaxStores) {
                throw new RuntimeException('Kuota yang disetujui lebih kecil dari jumlah toko yang sudah ada.');
            }

            $subscription = $organization->currentSubscription();

            $attributes = [
                'plan_id' => $customPlan->id,
                'status' => SubscriptionStatus::Active,
                'max_stores_override' => $approvedMaxStores,
                'max_owners_override' => $approvedMaxOwners,
                'trial_ends_at' => null,
            ];

            if ($subscription) {
                $subscription->update($attributes);
            } else {
                $organization->subscriptions()->create($attributes + [
                    'current_period_start' => now(),
                ]);
            }

            $request->update([
                'status' => CustomPlanRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $request->fresh();
        });

        $request->requestedBy->notify(new CustomPlanRequestReviewedNotification($request));

        return $request;
    }

    /**
     * Reject a custom plan request without touching the organization's
     * subscription at all.
     */
    public function reject(CustomPlanRequest $request, User $reviewer): CustomPlanRequest
    {
        $request->update([
            'status' => CustomPlanRequestStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $request = $request->fresh();

        $request->requestedBy->notify(new CustomPlanRequestReviewedNotification($request));

        return $request;
    }
}
