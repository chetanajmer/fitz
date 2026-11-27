<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change of a member's consent (accepted or withdrawn). The latest row per type is the current state. */
class MemberConsent extends Model
{
    use BelongsToGym;

    public const AI_COACH = 'ai_coach';

    public const PROGRESS_PHOTOS = 'progress_photos';

    public const TYPES = [self::PROGRESS_PHOTOS, self::AI_COACH];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['accepted' => 'boolean', 'version' => 'integer', 'accepted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
