<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LibrarySection extends Model
{
    protected $fillable = ['title', 'subtitle', 'body', 'sort_order'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LibraryPlan::class, 'library_plan_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
