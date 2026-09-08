<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Exceptions\StoreQuotaExceededException;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team (toko) under the given organization and add the
     * user as owner.
     *
     * @throws StoreQuotaExceededException when the organization's
     *         subscription plan does not have room for another store.
     */
    public function handle(User $user, string $name, Organization $organization, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $organization, $isPersonal) {
            // Lock the organization row so two concurrent "create store"
            // requests can't both slip past the quota check at once.
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $this->assertStoreQuotaAvailable($organization);

            $team = Team::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $membership = $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }

    /**
     * @throws StoreQuotaExceededException
     */
    private function assertStoreQuotaAvailable(Organization $organization): void
    {
        $subscription = $organization->currentSubscription();
        $limit = $subscription?->effectiveMaxStores();

        // null limit = unlimited (negotiated custom plan without an
        // explicit override yet, or a plan with no cap).
        if ($limit === null) {
            return;
        }

        $currentStoreCount = $organization->teams()->count();

        if ($currentStoreCount >= $limit) {
            throw new StoreQuotaExceededException($organization, $limit);
        }
    }
}
