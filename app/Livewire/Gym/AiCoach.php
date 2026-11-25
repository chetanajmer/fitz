<?php

namespace App\Livewire\Gym;

use App\Models\AiReport;
use App\Models\GymAiSetting;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\AiAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Gym admin: switch AI coach on, choose who may use it and the per-member monthly limit. */
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
        ], ['planIds.min' => 'Pick at least one plan.', 'memberLimit.max' => 'Gymai allows at most :max credits per member per month.'], ['memberLimit' => 'credits per member']);

        $settings->fill([
            'enabled' => $this->enabled,
            'eligibility' => $this->eligibility,
            'eligible_plan_ids' => array_map('intval', $this->planIds),
            'member_limit' => $this->memberLimit,
            'auto_monthly_report' => $this->autoMonthlyReport,
        ])->save();

        $this->dispatch('notify', message: 'AI coach settings saved.');
    }

    public function render(AiAccessService $access): View
    {
        $gym = current_gym();

        return view('livewire.gym.ai-coach', [
            'settings' => $access->settingsFor($gym),
            'platformEnabled' => $access->platformEnabled(),
            'costs' => [AiReport::PROGRESS => $access->cost(AiReport::PROGRESS), AiReport::PHOTO_COMPARISON => $access->cost(AiReport::PHOTO_COMPARISON)],
            'used' => $access->gymUsed($gym),
            'plans' => MembershipPlan::orderBy('name')->get(['id', 'name']),
            'selectedCount' => Member::where('ai_access', true)->count(),
            'consentedCount' => Member::whereNotNull('ai_consent_at')->count(),
            'reports' => AiReport::where('status', '!=', AiReport::ERASED)->with('member:id,gym_id,trainer_id,name,member_code')->latest('id')->limit(15)->get(),
        ]);
    }
}
