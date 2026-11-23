<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** A message a gym sends to a group of its own members (push + in-app notification list). */
class Announcement extends Model
{
    use BelongsToGym;

    public const PENDING = 'pending';     // waiting for Super Admin review

    public const APPROVED = 'approved';   // approved, the gym can send it

    public const REJECTED = 'rejected';

    public const SCHEDULED = 'scheduled'; // approved and sent at scheduled_at

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const CANCELLED = 'cancelled';

    /** status => [label, badge colour] */
    public const STATUSES = [
        self::PENDING => ['Waiting for approval', 'amber'],
        self::APPROVED => ['Approved', 'green'],
        self::REJECTED => ['Not approved', 'red'],
        self::SCHEDULED => ['Scheduled', 'blue'],
        self::SENDING => ['Sending', 'amber'],
        self::SENT => ['Sent', 'green'],
        self::CANCELLED => ['Withdrawn', 'gray'],
    ];

    public const AUDIENCES = [
        'active' => 'All active members',
        'all' => 'Everyone',
        'expiring' => 'Membership ending in the next 7 days',
        'expired' => 'Expired members',
        'trainer' => 'Members of one trainer',
    ];

    // Written by PushNotificationService only.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'recipients_count' => 'integer',
            'pushed_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MemberNotification::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'gray';
    }

    public function audienceLabel(): string
    {
        return $this->audience === 'trainer' && $this->trainer
            ? 'Members of '.$this->trainer->name
            : (self::AUDIENCES[$this->audience] ?? ucfirst($this->audience));
    }
}
