<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Exceptions\OrganizationMemberQuotaExceededException;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InviteOrganizationMemberAction
{
    /**
     * Invite a user to become an owner/manager of the organization.
     * Only relevant in practice for plans that allow more than 1 owner
     * (the "custom" plan) — basic/premium/ultra all have max_owners = 1,
     * so this will refuse a second invite on those plans.
     *
     * @throws OrganizationMemberQuotaExceededException
     */
    public function execute(Organization $organization, string $email, OrganizationRole $role, User $invitedBy): OrganizationInvitation
    {
        return DB::transaction(function () use ($organization, $email, $role, $invitedBy) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $this->assertMemberQuotaAvailable($organization);

            $invitation = $organization->invitations()->create([
                'email' => $email,
                'role' => $role,
                'invited_by' => $invitedBy->id,
                'expires_at' => now()->addDays(3),
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new OrganizationInvitationNotification($invitation));

            return $invitation;
        });
    }

    /**
     * @throws OrganizationMemberQuotaExceededException
     */
    private function assertMemberQuotaAvailable(Organization $organization): void
    {
        $subscription = $organization->currentSubscription();
        $limit = $subscription?->effectiveMaxOwners();

        if ($limit === null) {
            return;
        }

        $currentMemberCount = $organization->memberships()->count();

        if ($currentMemberCount >= $limit) {
            throw new OrganizationMemberQuotaExceededException($organization, $limit);
        }
    }
}
