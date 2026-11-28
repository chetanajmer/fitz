<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\StaffAppSections;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:staff (staff mobile app). Scopes the request to the staff user's own gym and blocks
 * accounts that were switched off, gyms that are not active and tokens of users who are no longer staff.
 *
 * The user is also set on the web guard for this request only (no session is written), so the policies,
 * permission checks and services shared with the web panel see the same signed-in staff user.
 */
class SetStaffApiContext
{
    public function __construct(protected TenantManager $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        // auth:staff made "staff" the default guard; roles and permissions are stored for "web".
        Auth::shouldUse('web');

        if (! $user instanceof User) {
            throw new ApiException('Unauthenticated.', 'UNAUTHENTICATED', 401);
        }

        $gym = $user->gym;

        if (! $user->isActive() || ! ($user->isGymAdmin() || $user->isTrainer()) || ! $gym || ! $gym->isOperational()) {
            throw new ApiException(
                $gym && ! $gym->isOperational() ? 'Your gym is currently unavailable on Fitnix.' : 'Your account is inactive. Please contact your gym.',
                'ACCOUNT_DISABLED',
                403,
                ['gym_status' => $gym?->status?->value],
            );
        }

        $this->tenant->set($gym);
        Auth::guard('web')->setUser($user);

        $response = $next($request);

        // Lets the app notice when the gym admin changed this trainer's access and reload GET /admin/me.
        $response->headers->set('X-Staff-Access', StaffAppSections::version($user));

        return $response;
    }
}
