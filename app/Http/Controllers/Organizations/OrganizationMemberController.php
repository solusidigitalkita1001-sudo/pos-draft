<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\ChangeOrganizationMemberRoleAction;
use App\Actions\Organizations\RemoveOrganizationMemberAction;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationMemberRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrganizationMemberController extends Controller
{
    /**
     * List current members + pending invitations. Only meaningfully
     * useful on the "custom" plan (max_owners > 1) but accessible
     * regardless — an organization can always see who's on it.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = $user->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');

        $subscription = $organization->currentSubscription();

        return Inertia::render('organizations/members', [
            'organization' => [
                'name' => $organization->name,
            ],
            'canManage' => $organization->ownedBy($user),
            'quota' => [
                'memberCount' => $organization->memberships()->count(),
                'maxOwners' => $subscription?->effectiveMaxOwners(),
            ],
            'members' => $organization->memberships()
                ->with('user')
                ->get()
                ->map(fn ($membership) => [
                    'userId' => $membership->user_id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                    'roleLabel' => $membership->role->label(),
                    'isSelf' => $membership->user_id === $user->id,
                ]),
            'pendingInvitations' => $organization->invitations()
                ->whereNull('accepted_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'roleLabel' => $invitation->role->label(),
                ]),
        ]);
    }

    /**
     * Change a member's role (owner ↔ manager).
     */
    public function update(UpdateOrganizationMemberRoleRequest $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        $organization = $currentUser->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($currentUser), 403, 'Hanya owner yang dapat mengubah role anggota.');

        try {
            (new ChangeOrganizationMemberRoleAction)->execute(
                organization: $organization,
                member: $user,
                newRole: OrganizationRole::from($request->validated('role')),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role anggota diperbarui.')]);

        return to_route('organizations.members.index');
    }

    /**
     * Remove a member from the organization.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        $organization = $currentUser->currentOrganization;

        abort_unless($organization, 403, 'Anda belum memiliki organization aktif.');
        abort_unless($organization->ownedBy($currentUser), 403, 'Hanya owner yang dapat menghapus anggota.');

        try {
            (new RemoveOrganizationMemberAction)->execute($organization, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Anggota dihapus.')]);

        return to_route('organizations.members.index');
    }
}
