<?php

namespace App\Models;

use App\Enums\FitnessPlanType;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A diet chart or workout plan. member_id = null → default plan for every member;
 * member_id set → customised plan visible only to that member.
 */
class FitnessPlan extends Model
{
    use BelongsToGym, SoftDeletes;

    protected $fillable = [
        'type', 'plan_category_id', 'title', 'summary', 'cover_image_path', 'level',
        'duration_weeks', 'daily_calories', 'is_published', 'sort_order',
    ];

    public const LEVELS = ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'];

    protected function casts(): array
    {
        return [
            'type' => FitnessPlanType::class,
            'is_published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PlanCategory::class, 'plan_category_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FitnessPlanSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCustom(): bool
    {
        return $this->member_id !== null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }

    public function scopeOfType(Builder $query, FitnessPlanType|string $type): Builder
    {
        return $query->where('type', $type instanceof FitnessPlanType ? $type->value : $type);
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->whereNull('member_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Plans a member may see: published defaults + their own published custom plans. */
    public function scopeVisibleToMember(Builder $query, Member $member): Builder
    {
        return $query->published()->where(fn ($q) => $q->whereNull('member_id')->orWhere('member_id', $member->id));
    }
}
