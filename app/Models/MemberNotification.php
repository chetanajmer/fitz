<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A notification a member received: shown in the app's notification list and pushed to their phones. */
class MemberNotification extends Model
{
    use BelongsToGym;

    // Written by PushNotificationService only.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_url ?? ($data['image_url'] ?? null),
            'screen' => $data['screen'] ?? 'home',
            'data' => $data ?: (object) [],
            'is_read' => $this->read_at !== null,
            // In the gym's time zone, e.g. 2026-11-24T10:00:00+05:30
            'read_at' => $this->read_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
            'created_at' => $this->created_at?->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
