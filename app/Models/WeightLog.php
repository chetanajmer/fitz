<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightLog extends Model
{
    use BelongsToGym;

    protected $table = 'member_weight_logs';

    // member_id, source and recorded_by are set by WeightTrackingService.
    protected $fillable = ['weight_kg', 'measured_on', 'note'];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'measured_on' => 'date',
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
}
