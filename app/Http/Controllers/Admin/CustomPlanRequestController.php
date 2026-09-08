<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Organizations\ReviewCustomPlanRequestAction;
use App\Enums\CustomPlanRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewCustomPlanRequestRequest;
use App\Models\CustomPlanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CustomPlanRequestController extends Controller
{
    /**
     * List every custom plan request, across ALL organizations —
     * this is the one screen in the app that's intentionally NOT
     * scoped to a single team/organization.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', CustomPlanRequestStatus::Pending->value);

        $requests = CustomPlanRequest::query()
            ->with(['organization', 'requestedBy', 'reviewedBy'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->get()
            ->map(fn (CustomPlanRequest $customPlanRequest) => [
                'id' => $customPlanRequest->id,
                'organizationName' => $customPlanRequest->organization->name,
                'requestedByName' => $customPlanRequest->requestedBy->name,
                'requestedByEmail' => $customPlanRequest->requestedBy->email,
                'requestedMaxStores' => $customPlanRequest->requested_max_stores,
                'requestedMaxOwners' => $customPlanRequest->requested_max_owners,
                'currentStoreCount' => $customPlanRequest->organization->teams()->count(),
                'message' => $customPlanRequest->message,
                'status' => $customPlanRequest->status->value,
                'statusLabel' => $customPlanRequest->status->label(),
                'reviewedByName' => $customPlanRequest->reviewedBy?->name,
                'reviewedAt' => $customPlanRequest->reviewed_at?->toISOString(),
                'createdAt' => $customPlanRequest->created_at->toISOString(),
            ]);

        return Inertia::render('admin/custom-plan-requests/index', [
            'requests' => $requests,
            'statusFilter' => $status,
        ]);
    }

    public function review(
        ReviewCustomPlanRequestRequest $request,
        CustomPlanRequest $customPlanRequest,
        ReviewCustomPlanRequestAction $reviewAction,
    ): RedirectResponse {
        abort_unless($customPlanRequest->status === CustomPlanRequestStatus::Pending, 422, 'Permintaan ini sudah direview.');

        if ($request->validated('decision') === 'approve') {
            try {
                $reviewAction->approve(
                    request: $customPlanRequest,
                    reviewer: $request->user(),
                    approvedMaxStores: $request->validated('approved_max_stores'),
                    approvedMaxOwners: $request->validated('approved_max_owners'),
                );
            } catch (RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Permintaan disetujui.')]);
        } else {
            $reviewAction->reject($customPlanRequest, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Permintaan ditolak.')]);
        }

        return to_route('admin.custom-plan-requests.index');
    }
}
