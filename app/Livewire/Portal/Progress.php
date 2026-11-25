<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\RecordsBodyProgress;
use App\Models\AiReport;
use App\Models\Member;
use App\Services\AiAccessService;
use App\Services\TransformationService;
use App\Services\WeightTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The signed-in member's transformation: photos, measurements, AI consent and AI reports. */
#[Layout('components.layouts.portal')]
#[Title('My progress')]
class Progress extends Component
{
    use RecordsBodyProgress;

    public function mount(): void
    {
        $this->resetForms();
    }

    public function setConsent(bool $accepted, AiAccessService $access): void
    {
        $access->setConsent($this->progressMember(), $accepted);
        $this->dispatch('notify', message: $accepted ? 'Thanks! AI coach is ready.' : 'Consent withdrawn. Your AI reports and progress photos were deleted.');
    }

    /** Always the signed-in member's own profile, never taken from the request. */
    protected function progressMember(): Member
    {
        $member = Auth::user()->memberProfile()->first();
        abort_unless($member, 403, 'No member profile is linked to this account.');

        return $member;
    }

    protected function authorizeProgress(): void {}

    protected function progressActor(): array
    {
        return ['app', null];
    }

    public function render(AiAccessService $access, TransformationService $transformation): View
    {
        $member = $this->progressMember();

        $summary = $transformation->summary($member);

        return view('livewire.portal.progress', [
            'status' => $access->status($member),
            'measurements' => $member->measurements()->get()->reverse(),
            'photos' => $member->progressPhotos()->get(),
            'transformation' => $summary,
            'highlight' => fn (AiReport $report) => $transformation->highlight($report, $member, $summary),
            'shareName' => Str::before($member->name, ' '),
            'photoUrl' => fn (int $id) => route('portal.progress-photo', $id),
            'reports' => $member->aiReports()->with(['beforePhoto', 'afterPhoto', 'requester'])->limit(20)->get(),
            'weight' => app(WeightTrackingService::class)->summary($member, 200),
            'visits' => $this->visitSeries($member),
        ]);
    }

    /**
     * Visits per week (last 12 weeks) and per month (last 6 months), oldest first, for the attendance chart.
     *
     * @return array{weekly: list<array{0: string, 1: int}>, monthly: list<array{0: string, 1: int}>}
     */
    protected function visitSeries(Member $member): array
    {
        $from = Carbon::today()->startOfMonth()->subMonths(5)->min(Carbon::today()->startOfWeek()->subWeeks(11));
        $dates = $member->attendances()->reorder()->where('date', '>=', $from)->pluck('date')->map(fn ($d) => Carbon::parse($d));

        $weekly = collect(range(11, 0))->map(function (int $ago) use ($dates) {
            $start = Carbon::today()->startOfWeek()->subWeeks($ago);

            return [$ago === 0 ? 'This week' : $start->format('d M'), $dates->filter(fn (Carbon $d) => $d->betweenIncluded($start, $start->copy()->endOfWeek()))->count()];
        });
        $monthly = collect(range(5, 0))->map(function (int $ago) use ($dates) {
            $start = Carbon::today()->startOfMonth()->subMonths($ago);

            return [$start->format('M Y'), $dates->filter(fn (Carbon $d) => $d->isSameMonth($start))->count()];
        });

        return ['weekly' => $weekly->values()->all(), 'monthly' => $monthly->values()->all()];
    }
}
