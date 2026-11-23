<?php

namespace App\Models;

use App\Enums\FitnessPlanType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** Platform-level default library category (Super Admin managed, not gym-scoped). */
class LibraryCategory extends Model
{
    protected $fillable = ['name', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => FitnessPlanType::class];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(LibraryPlan::class)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
