<?php

namespace App\Http\Resources\V1;

use App\Models\Gym;
use App\Models\GymPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Gym */
class GymBrandingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = $this->allSettings();

        return [
            'gym_id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'is_active' => $this->isOperational(),
            'tagline' => $settings['tagline'],
            'description' => $this->description,
            'logo_url' => $this->logoUrl(),
            'banner_url' => $this->bannerUrl(),
            'colors' => [
                'primary' => $this->primaryColor(),
                'secondary' => $this->secondaryColor(),
            ],
            'contact' => [
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'city' => $this->city,
                'whatsapp' => $settings['social']['whatsapp'] ?? null,
            ],
            'social' => collect($settings['social'])->except('whatsapp')->all(),
            'opening_hours' => collect($settings['opening_hours'])->map(fn ($h, $day) => [
                'day' => $day,
                'open' => $h['closed'] ? null : $h['open'],
                'close' => $h['closed'] ? null : $h['close'],
                'closed' => (bool) $h['closed'],
            ])->values()->all(),
            'facilities' => array_values($settings['facilities']),
            'photos' => $this->photos()->get()->map(fn (GymPhoto $p) => ['url' => $p->url(), 'caption' => $p->caption])->all(),
            'public_url' => $this->publicUrl(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
