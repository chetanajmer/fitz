<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One block of a plan: title + subtitle + text + optional photo. Scoped through its plan. */
class FitnessPlanSection extends Model
{
    protected $fillable = ['title', 'subtitle', 'meal_time', 'body', 'image_path', 'sort_order'];

    protected static function booted(): void
    {
        // Meal time (diet charts): taken from a subtitle such as "8:30 AM · ~300 kcal", unless it was set itself.
        static::saving(function (FitnessPlanSection $section) {
            if (! $section->isDirty('meal_time') && $section->isDirty('subtitle')) {
                $section->meal_time = self::parseTime($section->subtitle);
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FitnessPlan::class, 'fitness_plan_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** "08:30" or null. */
    public function mealClock(): ?string
    {
        return $this->meal_time ? substr($this->meal_time, 0, 5) : null;
    }

    /** The first clock time in a text ("8:30 AM", "by 1 PM", "20:00") as "HH:MM", or null. */
    public static function parseTime(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        if (preg_match('/\b(1[0-2]|0?[1-9])(?:[:.]([0-5]\d))?\s*([AaPp])\.?\s*[Mm]\b/', $text, $m)) {
            $hour = (int) $m[1] % 12 + (strtolower($m[3]) === 'p' ? 12 : 0);

            return sprintf('%02d:%02d', $hour, (int) ($m[2] ?? 0));
        }

        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $text, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }
}
