<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\StaffAppSections;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff API permission check. "staff.can:members.view" needs that permission;
 * "staff.can:plans.manage|memberships.manage" needs any one of them.
 */
class EnsureStaffPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user('staff');

        foreach (explode('|', $permissions) as $permission) {
            if ($user?->can($permission)) {
                return $next($request);
            }
        }

        // The current sections let the app hide what the gym admin has just switched off.
        throw new ApiException('You do not have access to this.', 'FORBIDDEN', 403, $user ? [
            'sections' => StaffAppSections::for($user),
            'access_version' => StaffAppSections::version($user),
        ] : []);
    }
}
