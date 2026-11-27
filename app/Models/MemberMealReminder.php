<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A meal reminder for one day, built from the member's diet program (ReminderService::buildMealReminders). */
class MemberMealReminder extends Model
{
    use BelongsToGym;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'send_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FitnessPlanSection::class, 'plan_section_id');
    }
}
