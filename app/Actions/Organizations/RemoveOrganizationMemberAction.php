<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemoveOrganizationMemberAction
{
    /**
     * Remove a member from the organization. Refuses to remove the last
     * remaining owner — an organization must always have at least one,
     * or nobody would be left able to manage billing/stores for it.
     *
     * @throws RuntimeException
     */
    public function execute(Organization $organization, User $member): void
    {
        DB::transaction(function () use ($organization, $member) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $membership = $organization->memberships()
                ->where('user_id', $member->id)
                ->first();

            if (! $membership) {
                return;
            }

            if ($membership->role === OrganizationRole::Owner) {
                $ownerCount = $organization->memberships()
                    ->where('role', OrganizationRole::Owner->value)
                    ->count();

                if ($ownerCount <= 1) {
                    throw new RuntimeException('Tidak bisa menghapus owner terakhir dari organization.');
                }
            }

            $membership->delete();

            // Kalau member yang dihapus sedang "berada" di organization
            // ini, alihkan ke organization lain yang masih dia punya.
            if ($member->current_organization_id === $organization->id) {
                $fallback = $member->fallbackOrganization($organization);
                $member->update(['current_organization_id' => $fallback?->id]);
            }
        });
    }
}
