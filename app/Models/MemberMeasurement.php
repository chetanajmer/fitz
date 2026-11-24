<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberMeasurement extends Model
{
    use BelongsToGym;

    /** Body measurements in cm, plus body fat in %. */
    public const FIELDS = [
        'chest_cm' => 'Chest (cm)',
        'waist_cm' => 'Waist (cm)',
        'hips_cm' => 'Hips (cm)',
        'arm_cm' => 'Arm (cm)',
        'thigh_cm' => 'Thigh (cm)',
        'body_fat_percent' => 'Body fat (%)',
    ];

    // member_id, source and recorded_by are set by BodyProgressService.
    protected $fillable = ['measured_on', 'chest_cm', 'waist_cm', 'hips_cm', 'arm_cm', 'thigh_cm', 'body_fat_percent'];

    protected function casts(): array
    {
        return array_fill_keys(array_keys(self::FIELDS), 'float') + ['measured_on' => 'date'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
