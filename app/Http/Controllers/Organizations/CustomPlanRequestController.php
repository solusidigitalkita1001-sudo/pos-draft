<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\RequestCustomPlanAction;
use App\Enums\CustomPlanRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreCustomPlanRequestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomPlanRequestController extends Controller
{
    /**
     * Form for the organization owner to ask for a custom store/owner
     * quota (paket "custom" dari roadmap — dikoordinasikan manual, bukan
     * self-service checkout).
     */
    public function create(Request $request): Response
    {
        $organization = $request->user()->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');

        return Inertia::render('organizations/custom-plan-request', [
            'organization' => [
                'name' => $organization->name,
            ],
            'currentStoreCount' => $organization->teams()->count(),
            'pendingRequest' => $organization->customPlanRequests()
                ->where('status', CustomPlanRequestStatus::Pending->value)
                ->latest()
                ->first()?->only(['id', 'requested_max_stores', 'requested_max_owners', 'message']),
        ]);
    }

    public function store(StoreCustomPlanRequestRequest $request, RequestCustomPlanAction $requestCustomPlan): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengajukan permintaan paket custom.');

        $requestCustomPlan->execute(
            organization: $organization,
            requestedBy: $user,
            requestedMaxStores: $request->validated('requested_max_stores'),
            requestedMaxOwners: $request->validated('requested_max_owners'),
            message: $request->validated('message'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Permintaan paket custom terkirim. Tim kami akan meninjau dan menghubungi Anda.'),
        ]);

        return to_route('organizations.stores');
    }
}
