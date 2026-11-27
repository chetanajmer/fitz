<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToGym;

    /** Staff entered the check-in. */
    public const METHOD_MANUAL = 'manual';

    /** Staff scanned the member's own QR at the desk. */
    public const METHOD_QR = 'qr';

    /** "Check in now" button in the member portal / app. */
    public const METHOD_SELF = 'self';

    /** The member scanned the gym's QR code with the app. */
    public const METHOD_GYM_QR = 'gym_qr';

    /** method => [badge label, badge colour] */
    public const METHOD_BADGES = [
        self::METHOD_MANUAL => ['MANUAL', 'gray'],
        self::METHOD_QR => ['QR', 'indigo'],
        self::METHOD_SELF => ['SELF', 'purple'],
        self::METHOD_GYM_QR => ['GYM QR', 'green'],
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** How the check-in was made, for the member app: qr (scanned the gym's QR), app (button) or reception (staff). */
    public function source(): string
    {
        return match ($this->method) {
            self::METHOD_GYM_QR => 'qr',
            self::METHOD_SELF => 'app',
            default => 'reception',
        };
    }

    public function methodLabel(): string
    {
        return self::METHOD_BADGES[$this->method][0] ?? strtoupper((string) $this->method);
    }

    public function methodColor(): string
    {
        return self::METHOD_BADGES[$this->method][1] ?? 'gray';
    }

    public function durationLabel(): ?string
    {
        if (! $this->check_out_at) {
            return null;
        }

        $minutes = (int) $this->check_in_at->diffInMinutes($this->check_out_at);

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }
}
