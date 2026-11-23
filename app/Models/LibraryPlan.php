<?php

namespace App\Models;

use App\Enums\FitnessPlanType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Platform-level default library plan (Super Admin managed, not gym-scoped). */
class LibraryPlan extends Model
{
    protected $fillable = ['library_category_id', 'title', 'summary', 'level', 'duration_weeks', 'daily_calories', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => FitnessPlanType::class,
            'is_published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'library_category_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(LibrarySection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }
}
