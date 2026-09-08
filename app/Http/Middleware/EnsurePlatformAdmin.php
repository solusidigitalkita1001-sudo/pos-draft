<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the cross-tenant admin panel (e.g. reviewing custom plan
 * requests across ALL organizations). Deliberately independent from
 * Spatie's team-scoped roles/permissions, which are scoped to a single
 * team's context and don't fit a platform-wide concern like this.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user || ! $user->is_platform_admin, 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
