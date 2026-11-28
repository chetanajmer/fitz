<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\AiCreditRequest;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Services\Push\PushMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Extra AI credits on request:
 *  - a member asks their gym admin, who approves (from the gym's pool) or rejects;
 *  - a gym admin asks the Super Admin to add credits to the gym's monthly pool.
 * One open request at a time; approved credits count for the month they were approved in.
 */
class AiCreditRequestService
{
    public const MEMBER_MAX = 20;

    public const GYM_MIN = 10;

    public const GYM_MAX = 5000;

    public function __construct(
        protected AiAccessService $access,
        protected PushNotificationService $notifications,
    ) {}

    /**
     * The member asks their gym for more credits. Throws ApiException (code + message) when not allowed.
     */
    public function requestForMember(Member $member, int $credits, ?string $note = null): AiCreditRequest
    {
        $status = $this->access->status($member);

        $denial = match (true) {
            ! $status['available'] => ['AI_NOT_AVAILABLE', 'AI coach is not available for you right now.', 403],
            $credits < 1 || $credits > self::MEMBER_MAX => ['INVALID_CREDITS', 'Ask for between 1 and '.self::MEMBER_MAX.' credits.', 422],
            default => null,
        };

        if ($denial) {
            throw new ApiException($denial[1], $denial[0], $denial[2]);
        }

        return DB::transaction(function () use ($member, $credits, $note) {
            Member::whereKey($member->id)->lockForUpdate()->first();

            if (AiCreditRequest::forGym($member->gym_id)->where('member_id', $member->id)->pending()->exists()) {
                throw new ApiException('You already asked your gym for more credits. Please wait for their answer.', 'REQUEST_PENDING', 409);
            }

            return $this->create($member->gym_id, ['member_id' => $member->id, 'requested_by' => $member->user_id, 'credits' => $credits, 'note' => $this->cleanNote($note)]);
        });
    }

    /** The gym admin asks the Super Admin to add credits to the gym's pool. */
    public function requestForGym(Gym $gym, User $admin, int $credits, ?string $note = null): AiCreditRequest
    {
        $denial = match (true) {
            $credits < self::GYM_MIN || $credits > self::GYM_MAX => ['INVALID_CREDITS', 'Ask for between '.self::GYM_MIN.' and '.self::GYM_MAX.' credits.', 422],
            default => null,
        };

        if ($denial) {
            throw new ApiException($denial[1], $denial[0], $denial[2]);
        }

        return DB::transaction(function () use ($gym, $admin, $credits, $note) {
            Gym::whereKey($gym->id)->lockForUpdate()->first();

            if (AiCreditRequest::forGym($gym)->forGymPool()->pending()->exists()) {
                throw new ApiException('Your gym already has a request waiting for Fitnix. You will see the answer here.', 'REQUEST_PENDING', 409);
            }

            return $this->create($gym->id, ['member_id' => null, 'requested_by' => $admin->id, 'credits' => $credits, 'note' => $this->cleanNote($note)]);
        });
    }

    /** Approve a pending request, granting $granted credits (defaults to what was asked) for this month. */
    public function approve(AiCreditRequest $request, User $decider, ?int $granted = null): AiCreditRequest
    {
        $granted ??= $request->credits;
        $max = $request->member_id ? self::MEMBER_MAX : self::GYM_MAX;

        if ($granted < 1 || $granted > $max) {
            throw new ApiException("Grant between 1 and {$max} credits.", 'INVALID_CREDITS', 422);
        }

        return $this->decide($request, $decider, AiCreditRequest::APPROVED, $granted);
    }

    public function reject(AiCreditRequest $request, User $decider): AiCreditRequest
    {
        return $this->decide($request, $decider, AiCreditRequest::REJECTED, null);
    }

    protected function decide(AiCreditRequest $request, User $decider, string $status, ?int $granted): AiCreditRequest
    {
        $request = DB::transaction(function () use ($request, $decider, $status, $granted) {
            $locked = AiCreditRequest::forGym($request->gym_id)->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new ApiException('This request was already answered.', 'REQUEST_DECIDED', 409);
            }

            $locked->forceFill([
                'status' => $status,
                'granted_credits' => $granted,
                'applies_month' => $granted ? Carbon::today()->startOfMonth() : null,
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ])->save();

            return $locked;
        });

        if ($request->member_id && ($member = $request->member) && ! $member->trashed()) {
            $this->notifications->notify($member, PushMessage::make(
                'ai_credits_decided',
                $granted ? "{$granted} extra AI ".str('credit')->plural($granted).' added' : 'AI credit request declined',
                $granted ? 'Your gym added AI credits for this month. Open AI coach to use them.' : 'Your gym could not add AI credits right now. New credits arrive on the 1st.',
                ['request_id' => $request->id],
            ));
        }

        return $request;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function create(int $gymId, array $attributes): AiCreditRequest
    {
        $request = new AiCreditRequest($attributes);
        $request->gym_id = $gymId;
        $request->status = AiCreditRequest::PENDING;
        $request->save();

        return $request;
    }

    protected function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 300);
    }
}
