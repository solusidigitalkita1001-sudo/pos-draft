<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptOrganizationInvitationAction;
use App\Actions\Organizations\InviteOrganizationMemberAction;
use App\Actions\Organizations\ResendOrganizationInvitationAction;
use App\Enums\OrganizationRole;
use App\Exceptions\OrganizationMemberQuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\AcceptOrganizationInvitationRequest;
use App\Http\Requests\Organizations\CreateOrganizationInvitationRequest;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class OrganizationInvitationController extends Controller
{
    /**
     * Invite a user to become an owner/manager of the current
     * organization. Only owners can invite (checked here since there's
     * no per-organization Policy class yet — mirrors the ownedBy()
     * check already used in CheckoutController/upgrade).
     */
    public function store(CreateOrganizationInvitationRequest $request, InviteOrganizationMemberAction $inviteMember): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengundang anggota.');

        try {
            $inviteMember->execute(
                organization: $organization,
                email: $request->validated('email'),
                role: OrganizationRole::from($request->validated('role')),
                invitedBy: $user,
            );
        } catch (OrganizationMemberQuotaExceededException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Kuota akun owner/manager pada paket Anda sudah penuh.'),
            ]);

            return to_route('organizations.members.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Undangan terkirim.')]);

        return to_route('organizations.members.index');
    }

    /**
     * Cancel a pending invitation.
     */
    public function destroy(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization && $invitation->organization_id === $organization->id, 404);
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat membatalkan undangan.');

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Undangan dibatalkan.')]);

        return to_route('organizations.members.index');
    }

    /**
     * Resend a pending invitation email.
     */
    public function resend(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization && $invitation->organization_id === $organization->id, 404);
        abort_unless($organization->ownedBy($user), 403, 'Hanya owner yang dapat mengirim ulang undangan.');

        try {
            (new ResendOrganizationInvitationAction)->execute($invitation);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Undangan dikirim ulang.')]);

        return to_route('organizations.members.index');
    }

    /**
     * Accept an invitation and join the organization.
     */
    public function accept(AcceptOrganizationInvitationRequest $request, OrganizationInvitation $invitation, AcceptOrganizationInvitationAction $acceptInvitation): RedirectResponse
    {
        try {
            $acceptInvitation->execute($request->user(), $invitation);
        } catch (OrganizationMemberQuotaExceededException) {
            return redirect()->route('organizations.stores')->with(
                'error',
                __('Undangan tidak bisa diterima — kuota akun owner/manager organization ini sudah penuh.'),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Berhasil bergabung ke organization.')]);

        return to_route('organizations.stores');
    }
}
