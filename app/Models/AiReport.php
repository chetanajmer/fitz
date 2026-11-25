<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI report for a member. Also the AI usage ledger: credits, tokens and cost of every call.
 * Failed reports do not count against any limit.
 */
class AiReport extends Model
{
    use BelongsToGym;

    public const PROGRESS = 'progress';

    public const PHOTO_COMPARISON = 'photo_comparison';

    public const TYPES = [self::PROGRESS => 'Progress report', self::PHOTO_COMPARISON => 'Photo comparison'];

    /** How much visibly changed between two photos. */
    public const CHANGE_LEVELS = ['none' => 'No visible change', 'slight' => 'Slight change', 'moderate' => 'Clear change', 'strong' => 'Strong change', 'unclear' => 'Not comparable'];

    /** Whether the change is good for the member. Reports made before this was asked have none. */
    public const DIRECTIONS = ['improved', 'mixed', 'declined', 'unknown'];

    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /** The member withdrew consent: the content is gone, only the usage (credits, tokens, cost) is kept. Never listed. */
    public const ERASED = 'erased';

    public const DISCLAIMER = 'AI-generated guidance, not medical advice. Talk to your trainer or a doctor before changing your diet or training because of it.';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'result' => 'array',
            'cost_usd' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function beforePhoto(): BelongsTo
    {
        return $this->belongsTo(ProgressPhoto::class, 'before_photo_id');
    }

    public function afterPhoto(): BelongsTo
    {
        return $this->belongsTo(ProgressPhoto::class, 'after_photo_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user');
    }

    /** Reports that use credits (pending or completed) in the month of the given date. */
    public function scopeCountingInMonth(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= Carbon::now();

        return $query->where('status', '!=', self::FAILED)
            ->whereBetween('created_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()]);
    }
}
