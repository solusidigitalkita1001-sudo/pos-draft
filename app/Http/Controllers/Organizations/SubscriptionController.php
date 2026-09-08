<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CancelPlanDowngradeAction;
use App\Actions\Organizations\CancelSubscriptionAction;
use App\Actions\Organizations\RequestPlanDowngradeAction;
use App\Actions\Organizations\ResumeSubscriptionAction;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\RequestPlanDowngradeRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class SubscriptionController extends Controller
{
    /**
     * Voluntarily cancel the organization's subscription. Takes effect
     * at the end of the current paid period (see CancelSubscriptionAction) —
     * access is NOT cut off immediately.
     */
    public function cancel(Request $request, CancelSubscriptionAction $cancelSubscription): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat membatalkan langganan.');

        try {
            $cancelSubscription->execute($organization);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Langganan dijadwalkan untuk berakhir di akhir periode saat ini.')]);

        return to_route('organizations.stores');
    }

    /**
     * Undo a pending cancellation.
     */
    public function resume(Request $request, ResumeSubscriptionAction $resumeSubscription): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengaktifkan ulang langganan.');

        try {
            $resumeSubscription->execute($organization);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pembatalan langganan dibatalkan — akun Anda tetap aktif.')]);

        return to_route('organizations.stores');
    }

    /**
     * Schedule a downgrade to a smaller/cheaper plan — takes effect at
     * the end of the current paid period (see RequestPlanDowngradeAction),
     * no payment involved.
     */
    public function downgrade(RequestPlanDowngradeRequest $request, RequestPlanDowngradeAction $requestDowngrade): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengubah paket.');

        $targetPlan = Plan::findByCode(PlanCode::from($request->validated('plan_code')));

        abort_unless($targetPlan, 404, 'Paket tidak ditemukan.');

        try {
            $requestDowngrade->execute($organization, $targetPlan);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Downgrade dijadwalkan — berlaku mulai akhir periode langganan saat ini.'),
        ]);

        return to_route('organizations.stores');
    }

    /**
     * Undo a scheduled downgrade.
     */
    public function cancelDowngrade(Request $request, CancelPlanDowngradeAction $cancelDowngrade): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengubah paket.');

        try {
            $cancelDowngrade->execute($organization);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Downgrade dibatalkan — paket Anda tetap seperti semula.')]);

        return to_route('organizations.stores');
    }
}
