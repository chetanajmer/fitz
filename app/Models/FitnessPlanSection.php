<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One block of a plan: title + subtitle + text + optional photo. Scoped through its plan. */
class FitnessPlanSection extends Model
{
    protected $fillable = ['title', 'subtitle', 'body', 'image_path', 'sort_order'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FitnessPlan::class, 'fitness_plan_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
