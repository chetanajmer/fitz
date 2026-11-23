<?php

namespace App\Models;

use App\Enums\FitnessPlanType;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Whether a member followed (done) or skipped a program day's workout or diet. */
class ProgramDayLog extends Model
{
    use BelongsToGym;

    public const STATUSES = ['done', 'skipped'];

    // Written by ProgramService only.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => FitnessPlanType::class,
            'date' => 'date',
            'program_day' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MemberProgram::class, 'member_program_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
