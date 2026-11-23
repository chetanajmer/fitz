<?php

namespace App\Http\Middleware;

use App\Enums\MemberStatus;
use App\Exceptions\ApiException;
use App\Models\Member;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:member. Scopes the request to the member's own gym and blocks
 * members who were deactivated after their token was issued.
 *
 * Usage: 'member.api' (also requires an active gym) or 'member.api:any-gym-status'
 * for endpoints that must report the gym status themselves (e.g. branding).
 */
class SetMemberApiContext
{
    public function __construct(protected TenantManager $tenant) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        /** @var Member|null $member */
        $member = $request->user('member');

        if (! $member instanceof Member) {
            throw new ApiException('Unauthenticated.', 'UNAUTHENTICATED', 401);
        }

        if ($member->status !== MemberStatus::Active) {
            $member->currentAccessToken()?->delete();

            throw new ApiException('Your membership account is inactive. Please contact your gym.', 'MEMBER_INACTIVE', 403);
        }

        $gym = $member->gym;

        if ($mode !== 'any-gym-status' && (! $gym || ! $gym->isOperational())) {
            throw new ApiException('Your gym is currently unavailable on Gymai.', 'GYM_INACTIVE', 403, ['gym_status' => $gym?->status?->value]);
        }

        $this->tenant->set($gym);

        return $next($request);
    }
}
