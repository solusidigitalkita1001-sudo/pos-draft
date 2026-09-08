<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChangeOrganizationMemberRoleAction
{
    /**
     * Change a member's role within the organization. Refuses to demote
     * the last remaining owner — same invariant as
     * RemoveOrganizationMemberAction (an organization must always have
     * at least one owner).
     *
     * @throws RuntimeException
     */
    public function execute(Organization $organization, User $member, OrganizationRole $newRole): void
    {
        DB::transaction(function () use ($organization, $member, $newRole) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $membership = $organization->memberships()
                ->where('user_id', $member->id)
                ->first();

            if (! $membership) {
                throw new RuntimeException('User ini bukan anggota organization.');
            }

            if ($membership->role === OrganizationRole::Owner && $newRole !== OrganizationRole::Owner) {
                $ownerCount = $organization->memberships()
                    ->where('role', OrganizationRole::Owner->value)
                    ->count();

                if ($ownerCount <= 1) {
                    throw new RuntimeException('Tidak bisa mengubah role owner terakhir dari organization.');
                }
            }

            $membership->update(['role' => $newRole]);
        });
    }
}
