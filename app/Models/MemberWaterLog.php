<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One glass (or bottle) of water the member logged. */
class MemberWaterLog extends Model
{
    use BelongsToGym;

    public const SOURCES = ['app', 'push'];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'logged_at' => 'datetime', 'ml' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
