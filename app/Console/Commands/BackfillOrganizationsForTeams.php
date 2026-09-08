<?php

namespace App\Console\Commands;

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('organizations:backfill')]
#[Description('Create an Organization for every existing team that does not have one yet, grouping teams by their current owner so existing multi-toko owners keep every store they already have.')]
class BackfillOrganizationsForTeams extends Command
{
    public function handle(CreateOrganizationAction $createOrganization): int
    {
        $teams = Team::query()
            ->whereNull('organization_id')
            ->get();

        if ($teams->isEmpty()) {
            $this->info('Tidak ada team yang perlu di-backfill.');

            return self::SUCCESS;
        }

        // Group teams by their current owner, so a user who already owns
        // multiple teams (allowed under the old flat model) gets ONE
        // organization covering all of them, on a plan sized to fit.
        $teamsByOwnerId = $teams->groupBy(function (Team $team) {
            return $team->owner()?->id ?? 'unowned';
        });

        foreach ($teamsByOwnerId as $ownerId => $ownerTeams) {
            if ($ownerId === 'unowned') {
                $this->warn("Melewati {$ownerTeams->count()} team tanpa owner yang jelas — perlu ditangani manual.");

                continue;
            }

            $owner = User::find($ownerId);

            if (! $owner) {
                continue;
            }

            $plan = $this->planFittingStoreCount($ownerTeams->count());

            DB::transaction(function () use ($owner, $ownerTeams, $plan, $createOrganization) {
                $organization = $createOrganization->execute(
                    user: $owner,
                    name: "{$owner->name}'s Organization",
                    plan: $plan,
                );

                // Existing customers are real, already-active users —
                // mark the subscription active immediately instead of
                // leaving them on a trial that could expire on them.
                $subscriptionUpdates = [
                    'status' => SubscriptionStatus::Active,
                    'trial_ends_at' => null,
                ];

                // Custom plan has no fixed max_stores — override it per
                // subscription so this organization's quota matches
                // what they already have (never below their own count).
                if ($plan->is_custom) {
                    $subscriptionUpdates['max_stores_override'] = $ownerTeams->count();
                }

                $organization->currentSubscription()?->update($subscriptionUpdates);

                foreach ($ownerTeams as $team) {
                    $team->update(['organization_id' => $organization->id]);
                }
            });

            $this->info("Organization dibuat untuk {$owner->email} ({$ownerTeams->count()} toko, plan: {$plan->code}).");
        }

        return self::SUCCESS;
    }

    /**
     * Pick the smallest plan that already fits how many stores this
     * owner currently has, so backfilling never puts an existing
     * customer over their own quota.
     */
    private function planFittingStoreCount(int $storeCount): Plan
    {
        $code = match (true) {
            $storeCount <= 1 => PlanCode::Basic,
            $storeCount <= 3 => PlanCode::Premium,
            $storeCount <= 7 => PlanCode::Ultra,
            default => PlanCode::Custom,
        };

        $plan = Plan::findByCode($code);

        if (! $plan) {
            throw new \RuntimeException("Plan '{$code->value}' belum ada — jalankan PlanSeeder dulu.");
        }

        return $plan;
    }
}
