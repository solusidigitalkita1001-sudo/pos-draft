<?php

namespace App\Actions\Organizations;

use App\Exceptions\OrganizationMemberQuotaExceededException;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptOrganizationInvitationAction
{
    /**
     * @throws OrganizationMemberQuotaExceededException when the
     *         organization's member quota is already full by the time
     *         this invitation gets accepted (invite-time and accept-time
     *         can be far apart, and quota can also change in between —
     *         so this is the authoritative check, not the one in
     *         InviteOrganizationMemberAction).
     */
    public function execute(User $user, OrganizationInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            $organization = Organization::whereKey($invitation->organization_id)->lockForUpdate()->firstOrFail();

            $isAlreadyMember = $organization->memberships()->where('user_id', $user->id)->exists();

            if (! $isAlreadyMember) {
                $this->assertMemberQuotaAvailable($organization);

                $organization->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            $user->switchOrganization($organization);
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

        if ($organization->memberships()->count() >= $limit) {
            throw new OrganizationMemberQuotaExceededException($organization, $limit);
        }
    }
}
