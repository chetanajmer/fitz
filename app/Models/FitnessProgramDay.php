<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FitnessProgramDay extends Model
{
    protected $fillable = ['day_number', 'title', 'is_rest', 'fitness_plan_id', 'notes'];

    protected function casts(): array
    {
        return ['is_rest' => 'boolean'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(FitnessProgram::class, 'fitness_program_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FitnessPlan::class, 'fitness_plan_id');
    }
}
