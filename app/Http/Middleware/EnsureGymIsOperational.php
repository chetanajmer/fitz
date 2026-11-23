<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gym-scoped users (gym_admin, trainer, member) must belong to an existing, active gym.
 * Suspended or deactivated gyms are redirected to an explanatory page.
 */
class EnsureGymIsOperational
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $gym = $user->gym;

        if (! $gym) {
            abort(403, 'Your account is not linked to a gym.');
        }

        if (! $gym->isOperational()) {
            return redirect()->route('gym.suspended');
        }

        return $next($request);
    }
}
