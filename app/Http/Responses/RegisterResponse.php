<?php

namespace App\Http\Responses;

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Teams\CreateTeam;
use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(
        private CreateOrganizationAction $createOrganization,
        private CreateTeam $createTeam,
    ) {
        //
    }

    public function toResponse($request): Response
    {
        $user = $request->user();

        // Normal path: CreateNewUser (Fortify action) already created the
        // organization + personal team during registration. This is a
        // defensive fallback for the rare case that didn't happen —
        // deliberately routed through the same Actions so we never end
        // up with a team that has no organization_id.
        $team = $user->personalTeam()
            ?? $user->teams()->orderBy('name')->first();

        if (! $team) {
            $organization = $user->currentOrganization
                ?? $this->createOrganization->execute(
                    user: $user,
                    name: $user->name."'s Organization",
                    plan: Plan::findByCode(PlanCode::Basic),
                );

            $team = $this->createTeam->handle(
                user: $user,
                name: $user->name."'s Team",
                organization: $organization,
                isPersonal: true,
            );
        }

        if (! $user->current_team_id) {
            $user->update(['current_team_id' => $team->id]);
            $user->setRelation('currentTeam', $team);
        }

        URL::defaults(['current_team' => $team->slug]);

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return redirect()->route('dashboard', ['current_team' => $team->slug]);
    }
}