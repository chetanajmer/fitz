<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Services\CheckInCodeService;
use App\Support\StaffAppSections;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Base for the staff app API (/api/v1/admin). Runs after auth:staff + staff.api, so the tenant is the
 * staff user's gym. Holds the response shapes shared by several endpoints.
 */
abstract class AdminController extends ApiController
{
    /** Minutes an "open" link works; a "share" link (?share=1) works for 7 days. */
    public const LINK_MINUTES = 10;

    public const SHARE_LINK_DAYS = 7;

    protected function staff(Request $request): User
    {
        return $request->user('staff');
    }

    protected function perPage(Request $request): int
    {
        return min(50, max(5, (int) $request->integer('per_page', 20)));
    }

    /** @return array{current_page:int, last_page:int, per_page:int, total:int} */
    protected function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }

    protected function forbidden(string $message = 'You do not have access to this.'): ApiException
    {
        return new ApiException($message, 'FORBIDDEN', 403);
    }

    /**
     * A signed link to a printable document page (receipt, invoice, Fitnix bill).
     *
     * @return array{url:string, expires_at:string, shareable:bool}
     */
    protected function documentLink(Request $request, string $route, array $parameters): array
    {
        $share = $request->boolean('share');
        $expires = $share ? now()->addDays(self::SHARE_LINK_DAYS) : now()->addMinutes(self::LINK_MINUTES);

        return [
            'url' => URL::temporarySignedRoute($route, $expires, $parameters),
            'expires_at' => $expires->toIso8601String(),
            'shareable' => $share,
        ];
    }

    /** A member the staff user can see (trainers without "see all members": only their own), else 404. */
    protected function visibleMemberOr404(Request $request, int $memberId): Member
    {
        return Member::visibleTo($this->staff($request))->find($memberId)
            ?? throw new ApiException('Member not found.', 'NOT_FOUND', 404);
    }

    protected function iso(?Carbon $time): ?string
    {
        return $time?->toIso8601String();
    }

    /** @return array<string, mixed> */
    protected function staffProfile(User $user): array
    {
        /** @var Gym $gym */
        $gym = $user->gym;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->isGymAdmin() ? 'gym_admin' : 'trainer',
                'photo_url' => $user->photoUrl(),
                'login_phone' => $user->login_phone,
                'login_phone_verified' => filled($user->login_phone),
            ],
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'slug' => $gym->slug,
                'logo_url' => $gym->logoUrl(),
                'status' => $gym->status?->value,
                'timezone' => (string) config('app.timezone'),
                'theme' => $gym->dashboardColors('admin'),
            ],
            'permissions' => StaffAppSections::permissionsOf($user),
            'sections' => StaffAppSections::for($user),
            'access_version' => StaffAppSections::version($user),
        ];
    }

    /** @return array{id:int, name:string}|null */
    protected function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }

    /** @return array<string, mixed> */
    protected function memberBrief(?Member $member): ?array
    {
        return $member ? [
            'id' => $member->id,
            'member_code' => $member->member_code,
            'name' => $member->name,
            'phone' => $member->phone,
            'photo_url' => $member->photoUrl(),
        ] : null;
    }

    /** One membership, with "status" from the shared enum: upcoming | active | expired | cancelled. */
    protected function membershipData(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'plan_id' => $membership->membership_plan_id,
            'plan_name' => $membership->plan_name,
            'start_date' => $membership->start_date->toDateString(),
            'end_date' => $membership->end_date->toDateString(),
            'status' => $membership->computedStatus()->value,
            'days_left' => $membership->daysRemaining(),
            'price' => (float) $membership->price,
            'discount' => (float) $membership->discount,
            'total_amount' => (float) $membership->total_amount,
            'amount_paid' => (float) $membership->amount_paid,
            'balance' => $membership->balance(),
            'payment_status' => $membership->paymentStatus(),
        ];
    }

    /** @return array<string, mixed> */
    protected function attendanceData(Attendance $attendance, bool $withMember = true): array
    {
        return array_filter([
            'id' => $attendance->id,
            'member' => $withMember ? $this->memberBrief($attendance->member) : false,
            'date' => $attendance->date?->toDateString(),
            'check_in_at' => $this->iso($attendance->check_in_at),
            'check_out_at' => $this->iso($attendance->check_out_at),
            'method' => $attendance->method,
            'method_label' => $attendance->methodLabel(),
        ], fn ($value) => $value !== false);
    }

    /**
     * The gym check-in QR as the staff app shows it.
     *
     * @return array<string, mixed>
     */
    protected function checkInQrData(Gym $gym, CheckInCodeService $codes): array
    {
        if ($codes->mode($gym) === CheckInCodeService::SCREEN) {
            $slot = intdiv(now()->timestamp, CheckInCodeService::SCREEN_SECONDS);

            return [
                'mode' => CheckInCodeService::SCREEN,
                'payload' => $codes->url($codes->screenCode($gym)),
                'refresh_seconds' => CheckInCodeService::SCREEN_SECONDS,
                'expires_at' => Carbon::createFromTimestamp(($slot + 1) * CheckInCodeService::SCREEN_SECONDS, config('app.timezone'))->toIso8601String(),
            ];
        }

        return [
            'mode' => CheckInCodeService::PRINTED,
            'payload' => $codes->url($codes->printedCode($gym)),
            'refresh_seconds' => null,
            'expires_at' => null,
        ];
    }
}
