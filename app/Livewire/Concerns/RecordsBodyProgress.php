<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ApiException;
use App\Models\AiReport;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\AiReportService;
use App\Services\BodyProgressService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;

/** Measurements, progress photos and AI reports of one member: used by gym staff and by the member's own portal. */
trait RecordsBodyProgress
{
    use WithFileUploads;

    abstract protected function progressMember(): Member;

    abstract protected function authorizeProgress(): void;

    /** @return array{0: string, 1: ?User} source ("staff" or "app") and the staff user */
    abstract protected function progressActor(): array;

    /** @var array<string, string> */
    public array $measurement = [];

    public $photo;

    public string $pose = 'front';

    public string $takenOn = '';

    public string $beforePhotoId = '';

    public string $afterPhotoId = '';

    public function saveMeasurement(BodyProgressService $body): void
    {
        $this->authorizeProgress();
        $rules = collect(BodyProgressService::measurementRules())->mapWithKeys(fn ($rule, $key) => ["measurement.{$key}" => $rule])->all();
        $data = $this->validate($rules, [], collect(MemberMeasurement::FIELDS)->mapWithKeys(fn ($label, $key) => ["measurement.{$key}" => strtolower($label)])->all());

        $body->recordMeasurement($this->progressMember(), $data['measurement'], ...$this->progressActor());
        $this->resetForms();
        $this->dispatch('notify', message: 'Measurements saved.');
    }

    public function deleteMeasurement(int $id, BodyProgressService $body): void
    {
        $this->authorizeProgress();
        $body->deleteMeasurement($this->progressMember(), $id);
    }

    public function uploadPhoto(BodyProgressService $body): void
    {
        $this->authorizeProgress();
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'pose' => ['required', Rule::in(array_keys(ProgressPhoto::POSES))],
            'takenOn' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $body->addPhoto($this->progressMember(), $this->photo, $this->pose, $this->takenOn, ...$this->progressActor());
        $this->resetForms();
        $this->dispatch('notify', message: 'Photo saved.');
    }

    public function deletePhoto(int $id, BodyProgressService $body): void
    {
        $this->authorizeProgress();
        $body->deletePhoto($this->progressMember(), $id);
    }

    public function requestReport(string $type, AiReportService $reports): void
    {
        $this->authorizeProgress();
        $isComparison = $type === AiReport::PHOTO_COMPARISON;

        if ($isComparison) {
            $this->validate(['beforePhotoId' => 'required|integer', 'afterPhotoId' => 'required|integer|different:beforePhotoId'], [], ['beforePhotoId' => 'before photo', 'afterPhotoId' => 'after photo']);
        }

        try {
            [$requestedBy, $staff] = $this->progressActor();
            $report = $reports->request($this->progressMember(), $isComparison ? AiReport::PHOTO_COMPARISON : AiReport::PROGRESS, $requestedBy === 'staff' ? 'staff' : 'member', $staff,
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
}
