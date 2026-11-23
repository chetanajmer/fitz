<?php

namespace App\Http\Resources\V1;

use App\Models\FitnessPlan;
use App\Models\FitnessPlanSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FitnessPlan
 *
 * Summary by default; ->withSections() adds the full sections list (detail endpoint).
 */
class FitnessPlanResource extends JsonResource
{
    protected bool $includeSections = false;

    public function withSections(): static
    {
        $this->includeSections = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'cover_image_url' => $this->coverUrl(),
            'is_custom' => $this->isCustom(),
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'level' => $this->level,
            'duration_weeks' => $this->duration_weeks,
            'daily_calories' => $this->daily_calories,
            'section_count' => $this->sections_count ?? $this->sections->count(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->includeSections) {
            $data['sections'] = $this->sections->map(fn (FitnessPlanSection $s) => [
                'id' => $s->id,
                'position' => $s->sort_order + 1,
                'title' => $s->title,
                'subtitle' => $s->subtitle,
                'body' => $s->body,
                'image_url' => $s->imageUrl(),
            ])->values()->all();
        }

        return $data;
    }
}
