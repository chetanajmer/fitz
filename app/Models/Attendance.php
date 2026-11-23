<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToGym;

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

    public function durationLabel(): ?string
    {
        if (! $this->check_out_at) {
            return null;
        }

        $minutes = (int) $this->check_in_at->diffInMinutes($this->check_out_at);

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }
}
