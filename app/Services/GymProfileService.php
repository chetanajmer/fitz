<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\GymPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GymProfileService
{
    public function __construct(protected CityService $cities) {}

    /**
     * @param  array  $profile  name, phone, email, address, description, city_id (from the managed list)
     * @param  array  $settings  primary_color, secondary_color, tagline, opening_hours, facilities, social
     */
    public function update(Gym $gym, array $profile, array $settings, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Gym
    {
        if (array_key_exists('city_id', $profile)) {
            $this->cities->assignToGym($gym, $this->cities->resolve($profile['city_id'] ? (int) $profile['city_id'] : null));
        }

        $gym->fill(collect($profile)->except(['city', 'city_id'])->all());

        $current = $gym->allSettings();
        // Merge over the current settings so keys managed elsewhere (e.g. streak rules) are kept.
        $gym->settings = array_merge($current, [
            'primary_color' => $settings['primary_color'] ?? $current['primary_color'],
            'secondary_color' => $settings['secondary_color'] ?? $current['secondary_color'],
            'tagline' => $settings['tagline'] ?? null,
            'opening_hours' => $this->normaliseHours($settings['opening_hours'] ?? $current['opening_hours']),
            'facilities' => array_values(array_filter(array_map('trim', $settings['facilities'] ?? []))),
            'social' => array_intersect_key($settings['social'] ?? [], Gym::DEFAULT_SETTINGS['social']),
        ]);

        if ($logo) {
            if ($gym->logo_path) {
                Storage::disk('public')->delete($gym->logo_path);
            }
            $gym->logo_path = $logo->store("gyms/{$gym->id}/branding", 'public');
        }

        if ($banner) {
            if ($gym->banner_path) {
                Storage::disk('public')->delete($gym->banner_path);
            }
            $gym->banner_path = $banner->store("gyms/{$gym->id}/branding", 'public');
        }

        $gym->save();

        return $gym;
    }

    public function removeLogo(Gym $gym): void
    {
        if ($gym->logo_path) {
            Storage::disk('public')->delete($gym->logo_path);
            $gym->logo_path = null;
            $gym->save();
        }
    }

    public function removeBanner(Gym $gym): void
    {
        if ($gym->banner_path) {
            Storage::disk('public')->delete($gym->banner_path);
            $gym->banner_path = null;
            $gym->save();
        }
    }

    public function addPhoto(Gym $gym, UploadedFile $file, ?string $caption = null): GymPhoto
    {
        $photo = new GymPhoto([
            'path' => $file->store("gyms/{$gym->id}/photos", 'public'),
            'caption' => $caption,
            'sort_order' => (int) GymPhoto::max('sort_order') + 1,
        ]);
        $photo->gym_id = $gym->id;
        $photo->save();

        return $photo;
    }

    public function deletePhoto(GymPhoto $photo): void
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }

    public function regenerateJoinToken(Gym $gym): Gym
    {
        $gym->join_token = Gym::generateJoinToken();
        $gym->save();

        return $gym;
    }

    public function regenerateRegisterToken(Gym $gym): Gym
    {
        $gym->register_token = Gym::generateRegisterToken();
        $gym->save();

        return $gym;
    }

    protected function normaliseHours(array $hours): array
    {
        $out = [];
        foreach (array_keys(Gym::DEFAULT_SETTINGS['opening_hours']) as $day) {
            $h = $hours[$day] ?? [];
            $out[$day] = [
                'open' => preg_match('/^\d{2}:\d{2}$/', $h['open'] ?? '') ? $h['open'] : '06:00',
                'close' => preg_match('/^\d{2}:\d{2}$/', $h['close'] ?? '') ? $h['close'] : '22:00',
                'closed' => (bool) ($h['closed'] ?? false),
            ];
        }

        return $out;
    }
}
