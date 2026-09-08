<?php

namespace App\Actions\Organizations;

use App\Enums\CustomPlanRequestStatus;
use App\Models\CustomPlanRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Organizations\CustomPlanRequestSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class RequestCustomPlanAction
{
    /**
     * Submit a custom plan request for the organization. Doesn't change
     * anything about the organization's subscription by itself — a
     * platform admin has to review and approve it first (see
     * ReviewCustomPlanRequestAction).
     */
    public function execute(
        Organization $organization,
        User $requestedBy,
        ?int $requestedMaxStores,
        ?int $requestedMaxOwners,
        ?string $message,
    ): CustomPlanRequest {
        $request = CustomPlanRequest::create([
            'organization_id' => $organization->id,
            'requested_by' => $requestedBy->id,
            'requested_max_stores' => $requestedMaxStores,
            'requested_max_owners' => $requestedMaxOwners,
            'message' => $message,
            'status' => CustomPlanRequestStatus::Pending,
        ]);

        $admins = User::where('is_platform_admin', true)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new CustomPlanRequestSubmittedNotification($request));
        }

        return $request;
    }
}
