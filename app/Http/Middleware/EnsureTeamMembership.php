<?php

namespace App\Http\Middleware;

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Teams\CreateTeam;
use App\Enums\PlanCode;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    public function __construct(
        private CreateOrganizationAction $createOrganization,
        private CreateTeam $createTeam,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $teamSlug = $request->route('current_team');

        if (! $teamSlug) {
            return $this->redirectToUserTeam($user);
        }

        // Query langsung ke DB — tidak pakai relasi cached
        $team = Team::where('slug', $teamSlug)->whereNull('deleted_at')->first();

        if (! $team) {
            return $this->redirectToUserTeam($user);
        }

        // Cek membership langsung dari DB (hindari relasi cached yang stale)
        $isMember = \DB::table('team_members')
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            return $this->redirectToUserTeam($user, "Anda bukan anggota tim \"{$team->name}\".");
        }

        // Blokir akses kalau subscription organization-nya sudah
        // suspended/canceled — arahkan ke halaman Toko Saya (di luar
        // prefix {current_team}) supaya user tetap bisa lihat status
        // & memperpanjang paket, tapi tidak bisa transaksi di toko.
        $subscription = $team->organization?->currentSubscription();

        if ($subscription && ! $subscription->isUsable()) {
            return redirect()
                ->route('organizations.stores')
                ->with('error', 'Akses toko dibatasi sementara karena langganan belum diperpanjang.');
        }

        // Set current team jika berbeda
        if ($user->current_team_id !== $team->id) {
            $user->update(['current_team_id' => $team->id]);
            $user->setRelation('currentTeam', $team);
            $user->current_team_id = $team->id;
        } else {
            // Pastikan relasi currentTeam tersedia
            if (! $user->relationLoaded('currentTeam')) {
                $user->setRelation('currentTeam', $team);
            }
        }

        // Set Spatie permission team context
        setPermissionsTeamId($team->id);

        return $next($request);
    }

    /**
     * Redirect user ke dashboard team mereka yang valid.
     * Jika tidak punya team, buat personal team dulu.
     */
    private function redirectToUserTeam(User $user, ?string $error = null): Response
    {
        // Query langsung, hindari relasi cached
        $currentTeamId = $user->current_team_id;

        if ($currentTeamId) {
            $isMember = \DB::table('team_members')
                ->where('team_id', $currentTeamId)
                ->where('user_id', $user->id)
                ->exists();

            if ($isMember) {
                $team = Team::find($currentTeamId);
                if ($team) {
                    return $this->buildRedirect($team, $error);
                }
            }
        }

        // Cari team lain yang dimiliki user
        $membership = \DB::table('team_members')
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->first();

        if ($membership) {
            $team = Team::find($membership->team_id);
            if ($team) {
                $user->update(['current_team_id' => $team->id]);
                return $this->buildRedirect($team, $error);
            }
        }

        // User tidak punya team sama sekali — buat personal team.
        // Selalu lewat CreateOrganizationAction + CreateTeam, supaya
        // TIDAK PERNAH ada team baru yang lolos tanpa organization_id
        // (itu akan bikin quota enforcement bolong).
        $team = $this->createPersonalTeam($user);
        return $this->buildRedirect($team, $error);
    }

    private function buildRedirect(Team $team, ?string $error): Response
    {
        $url = url("/{$team->slug}/dashboard");
        $redirect = redirect($url);

        if ($error) {
            $redirect->with('error', $error);
        }

        return $redirect;
    }

    /**
     * Buat personal team untuk user yang belum punya team.
     */
    private function createPersonalTeam(User $user): Team
    {
        $organization = $user->currentOrganization
            ?? $this->createOrganization->execute(
                user: $user,
                name: $user->name."'s Organization",
                plan: Plan::findByCode(PlanCode::Basic),
            );

        return $this->createTeam->handle(
            user: $user,
            name: $user->name."'s Team",
            organization: $organization,
            isPersonal: true,
        );
    }
}
