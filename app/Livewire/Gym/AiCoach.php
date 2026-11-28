<?php

namespace App\Livewire\Gym;

use App\Exceptions\ApiException;
use App\Models\AiCreditRequest;
use App\Models\AiReport;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\AiAccessService;
use App\Services\AiCreditRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gym admin: switch AI coach on, choose who may use it and the per-member monthly limit,
 * answer members' requests for extra credits and ask Fitnix for a bigger pool.
 */
#[Layout('components.layouts.gym')]
#[Title('AI Coach')]
class AiCoach extends Component
{
    public bool $enabled = false;

    public string $eligibility = 'all';

    /** @var list<int|string> */
    public array $planIds = [];

    public int $memberLimit = 4;

    public bool $autoMonthlyReport = false;

    /** Credits the gym admin grants per pending member request, keyed by request id. @var array<int, int|string> */
    public array $grant = [];

    public int|string $poolCredits = 100;

    public string $poolNote = '';

    public function mount(AiAccessService $access): void
    {
        $settings = $access->settingsFor(current_gym());
        $this->enabled = $settings->enabled;
        $this->eligibility = $settings->eligibility;
        $this->planIds = $settings->eligible_plan_ids ?? [];
        $this->memberLimit = $settings->member_limit;
        $this->autoMonthlyReport = $settings->auto_monthly_report;
    }

    public function save(AiAccessService $access): void
    {
        $settings = $access->settingsFor(current_gym());
        $this->validate([
            'eligibility' => ['required', Rule::in(array_keys(GymAiSetting::ELIGIBILITY))],
            'planIds' => ['array', $this->eligibility === 'plans' ? 'min:1' : 'nullable'],
            'planIds.*' => ['integer', Rule::exists('membership_plans', 'id')->where('gym_id', current_gym()->id)],
            'memberLimit' => ['required', 'integer', 'min:1', 'max:'.$settings->member_ceiling],
        ], ['planIds.min' => 'Pick at least one plan.', 'memberLimit.max' => 'Fitnix allows at most :max credits per member per month. Members who need more can request extra credits.'], ['memberLimit' => 'credits per member']);

        $settings->fill([
            'enabled' => $this->enabled,
            'eligibility' => $this->eligibility,
            'eligible_plan_ids' => array_map('intval', $this->planIds),
            'member_limit' => $this->memberLimit,
            'auto_monthly_report' => $this->autoMonthlyReport,
        ])->save();

        $this->dispatch('notify', message: 'AI coach settings saved.');
    }

    /** Approve a member's request, granting the amount typed next to it (or what they asked for). */
    public function approveMemberRequest(int $id, AiCreditRequestService $requests): void
    {
        $request = AiCreditRequest::fromMembers()->findOrFail($id);
        $granted = (int) ($this->grant[$id] ?? $request->credits);

        try {
            $requests->approve($request, Auth::user(), $granted);
        } catch (ApiException $e) {
            $this->addError("grant.{$id}", $e->getMessage());

            return;
        }

        unset($this->grant[$id]);
        $this->dispatch('notify', message: "{$granted} extra ".str('credit')->plural($granted)." added for {$request->member->name} this month.");
    }

    public function rejectMemberRequest(int $id, AiCreditRequestService $requests): void
    {
        $request = AiCreditRequest::fromMembers()->findOrFail($id);

        try {
            $requests->reject($request, Auth::user());
        } catch (ApiException $e) {
            $this->addError("grant.{$id}", $e->getMessage());

            return;
        }

        $this->dispatch('notify', message: "Request from {$request->member->name} declined.");
    }

    /** Ask the Super Admin for more credits in the gym's pool. */
    public function requestPoolCredits(AiCreditRequestService $requests): void
    {
        $this->validate([
            'poolCredits' => ['required', 'integer', 'min:'.AiCreditRequestService::GYM_MIN, 'max:'.AiCreditRequestService::GYM_MAX],
            'poolNote' => ['nullable', 'string', 'max:300'],
        ], [], ['poolCredits' => 'credits', 'poolNote' => 'note']);

        try {
            $requests->requestForGym(current_gym(), Auth::user(), (int) $this->poolCredits, $this->poolNote);
        } catch (ApiException $e) {
            $this->addError('poolCredits', $e->getMessage());

            return;
        }

        $this->reset('poolNote');
        $this->dispatch('notify', message: 'Request sent to Fitnix. You will see the answer here.');
    }

    public function render(AiAccessService $access): View
    {
        $gym = current_gym();
        $settings = $access->settingsFor($gym);

        return view('livewire.gym.ai-coach', [
            'settings' => $settings,
            'platformEnabled' => $access->platformEnabled(),
            'costs' => [AiReport::PROGRESS => $access->cost(AiReport::PROGRESS), AiReport::PHOTO_COMPARISON => $access->cost(AiReport::PHOTO_COMPARISON)],
            'used' => $access->gymUsed($gym),
            'pool' => $access->gymPool($settings),
            'poolExtra' => $access->gymExtra($gym),
            'plans' => MembershipPlan::orderBy('name')->get(['id', 'name']),
            'selectedCount' => Member::where('ai_access', true)->count(),
            'consentedCount' => Member::whereNotNull('ai_consent_at')->count(),
            'reports' => AiReport::where('status', '!=', AiReport::ERASED)->with('member:id,gym_id,trainer_id,name,member_code')->latest('id')->limit(15)->get(),
            'memberRequests' => AiCreditRequest::fromMembers()->pending()->with('member:id,gym_id,trainer_id,name,member_code')->oldest('id')->get(),
            'poolRequests' => AiCreditRequest::forGymPool()->latest('id')->limit(3)->get(),
        ]);
    }
}
