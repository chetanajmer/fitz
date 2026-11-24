<?php

namespace App\Livewire\Gym\Members;

use App\Exceptions\ApiException;
use App\Models\AiReport;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Services\AiAccessService;
use App\Services\AiReportService;
use App\Services\BodyProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** "AI Progress" tab on a member's page: measurements, progress photos and AI reports. */
class AiProgress extends Component
{
    use WithFileUploads;

    #[Locked]
    public Member $member;

    /** @var array<string, string> */
    public array $measurement = [];

    public $photo;

    public string $pose = 'front';

    public string $takenOn = '';

    public string $beforePhotoId = '';

    public string $afterPhotoId = '';

    public function mount(Member $member): void
    {
        Gate::authorize('useAi', $member);
        $this->member = $member;
        $this->resetForms();
    }

    public function saveMeasurement(BodyProgressService $body): void
    {
        Gate::authorize('useAi', $this->member);
        $rules = collect(BodyProgressService::measurementRules())->mapWithKeys(fn ($rule, $key) => ["measurement.{$key}" => $rule])->all();
        $data = $this->validate($rules, [], collect(MemberMeasurement::FIELDS)->mapWithKeys(fn ($label, $key) => ["measurement.{$key}" => strtolower($label)])->all());

        $body->recordMeasurement($this->member, $data['measurement'], 'staff', Auth::user());
        $this->resetForms();
        $this->dispatch('notify', message: 'Measurements saved.');
    }

    public function deleteMeasurement(int $id, BodyProgressService $body): void
    {
        Gate::authorize('useAi', $this->member);
        $body->deleteMeasurement($this->member, $id);
    }

    public function uploadPhoto(BodyProgressService $body): void
    {
        Gate::authorize('useAi', $this->member);
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'pose' => ['required', Rule::in(array_keys(ProgressPhoto::POSES))],
            'takenOn' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $body->addPhoto($this->member, $this->photo, $this->pose, $this->takenOn, 'staff', Auth::user());
        $this->resetForms();
        $this->dispatch('notify', message: 'Photo saved.');
    }

    public function deletePhoto(int $id, BodyProgressService $body): void
    {
        Gate::authorize('useAi', $this->member);
        $body->deletePhoto($this->member, $id);
    }

    /** Hand-pick the member for AI coach (gym eligibility "selected"). */
    public function toggleAccess(): void
    {
        Gate::authorize('useAi', $this->member);
        abort_unless(Auth::user()->can('ai.manage'), 403);
        $this->member->forceFill(['ai_access' => ! $this->member->ai_access])->save();
    }

    public function requestReport(string $type, AiReportService $reports): void
    {
        Gate::authorize('useAi', $this->member);
        $isComparison = $type === AiReport::PHOTO_COMPARISON;

        if ($isComparison) {
            $this->validate(['beforePhotoId' => 'required|integer', 'afterPhotoId' => 'required|integer|different:beforePhotoId'], [], ['beforePhotoId' => 'before photo', 'afterPhotoId' => 'after photo']);
        }

        try {
            $report = $reports->request($this->member, $isComparison ? AiReport::PHOTO_COMPARISON : AiReport::PROGRESS, 'staff', Auth::user(),
                $isComparison ? (int) $this->beforePhotoId : null, $isComparison ? (int) $this->afterPhotoId : null);
        } catch (ApiException $e) {
            $this->addError('report', $e->getMessage());

            return;
        }

        $this->dispatch('notify', message: $report->refresh()->status === AiReport::FAILED ? 'The AI report failed. No credits were used.' : 'AI report requested. It appears below when ready.');
    }

    protected function resetForms(): void
    {
        $this->measurement = array_fill_keys(array_keys(MemberMeasurement::FIELDS), '') + ['measured_on' => Carbon::today()->toDateString()];
        $this->reset('photo', 'beforePhotoId', 'afterPhotoId');
        $this->pose = 'front';
        $this->takenOn = Carbon::today()->toDateString();
    }

    public function render(AiAccessService $access): View
    {
        return view('livewire.gym.members.ai-progress', [
            'status' => $access->status($this->member),
            'measurements' => $this->member->measurements()->get()->reverse(),
            'photos' => $this->member->progressPhotos()->get(),
            'reports' => $this->member->aiReports()->limit(20)->get(),
        ]);
    }
}
