<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\UpgradePlanAction;
use App\Enums\PlanCode;
use App\Exceptions\StoreQuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpgradeOrganizationPlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * "Toko Saya" — list every store (Team) under the user's current
     * organization, with quota usage and available plans to upgrade to.
     */
    public function stores(Request $request): Response
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');

        $subscription = $organization->currentSubscription();
        $storeCount = $organization->teams()->count();
        $maxStores = $subscription?->effectiveMaxStores();
        $currentSortOrder = $subscription?->plan->sort_order ?? 0;

        return Inertia::render('organizations/stores', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->status->value,
                'statusLabel' => $subscription->status->label(),
                'planCode' => $subscription->plan->code,
                'planName' => $subscription->plan->name,
                'isCustom' => $subscription->plan->is_custom,
                'trialEndsAt' => $subscription->trial_ends_at?->toISOString(),
                'currentPeriodEnd' => $subscription->current_period_end?->toISOString(),
                'canceledAt' => $subscription->canceled_at?->toISOString(),
                'pendingPlanCode' => $subscription->pendingPlan?->code,
                'pendingPlanName' => $subscription->pendingPlan?->name,
            ] : null,
            'quota' => [
                'storeCount' => $storeCount,
                'maxStores' => $maxStores,
                'isFull' => $maxStores !== null && $storeCount >= $maxStores,
            ],
            'stores' => $organization->teams()
                ->orderBy('name')
                ->get()
                ->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'isPersonal' => $team->is_personal,
                ]),
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan) => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'maxStores' => $plan->max_stores,
                    'priceMonthly' => $plan->price_monthly,
                    'priceYearly' => $plan->price_yearly,
                    'isCustom' => $plan->is_custom,
                    'isCurrent' => $subscription?->plan_id === $plan->id,
                    'isDowngrade' => ! $plan->is_custom && $plan->sort_order < $currentSortOrder,
                ]),
            'canManage' => $organization->ownedBy($user),
        ]);
    }

    /**
     * Switch the organization's subscription to a different plan
     * (self-service, no payment gateway yet — see docs/18).
     */
    public function upgrade(UpgradeOrganizationPlanRequest $request, UpgradePlanAction $upgradePlan): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengubah paket.');

        $plan = Plan::findByCode(PlanCode::from($request->validated('plan_code')));

        abort_unless($plan, 404, 'Paket tidak ditemukan.');

        try {
            $upgradePlan->execute($organization, $plan);
        } catch (StoreQuotaExceededException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Jumlah toko Anda saat ini melebihi kuota paket tujuan. Hapus/nonaktifkan toko terlebih dahulu, atau pilih paket yang lebih besar.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paket berhasil diubah.')]);

        return to_route('organizations.stores');
    }
}
