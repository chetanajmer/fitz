<?php

namespace App\Livewire\Portal\Concerns;

use App\Enums\FitnessPlanType;
use App\Models\Member;
use App\Models\ProgramDayLog;
use App\Services\ProgramService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Member portal: mark today's workout / diet from the assigned program as done or skipped. */
trait LogsProgramDays
{
    public function logProgramDay(string $type, string $status, ProgramService $programs): void
    {
        abort_unless(in_array($type, FitnessPlanType::values(), true) && in_array($status, ProgramDayLog::STATUSES, true), 422);

        try {
            $programs->logDay($this->loggingMember(), FitnessPlanType::from($type), Carbon::today(), $status);
        } catch (ValidationException $e) {
            $this->addError('program', collect($e->errors())->flatten()->first());

            return;
        }

        $this->dispatch('notify', message: $status === 'done'
            ? 'Great job! Today\'s '.$type.' is marked as done.'
            : 'Today\'s '.$type.' is marked as skipped.');
    }

    public function undoProgramDay(string $type, ProgramService $programs): void
    {
        abort_unless(in_array($type, FitnessPlanType::values(), true), 422);

        try {
            $programs->clearDay($this->loggingMember(), FitnessPlanType::from($type), Carbon::today());
        } catch (ValidationException $e) {
            $this->addError('program', collect($e->errors())->flatten()->first());
        }
    }

    /** The signed-in member's own profile — never taken from request input. */
    protected function loggingMember(): Member
    {
        return Auth::user()->memberProfile()->firstOrFail();
    }
}
