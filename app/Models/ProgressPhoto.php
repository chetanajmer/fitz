<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A progress photo, stored on the private "local" disk and served only through authorised routes. */
class ProgressPhoto extends Model
{
    use BelongsToGym;

    public const POSES = ['front' => 'Front', 'side' => 'Side', 'back' => 'Back'];

    protected $table = 'member_progress_photos';

    // member_id, path, source and uploaded_by are set by BodyProgressService.
    protected $fillable = ['taken_on', 'pose'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['taken_on' => 'date'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
