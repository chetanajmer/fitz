<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\GymPhoto;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GymProfileService
{
    /** Logo and banner are compressed to these sizes on upload (KB = upper limit of the file). */
    public const IMAGE_RULES = [
        'logo' => ['width' => 512, 'height' => 512, 'max_kb' => 70],
        'banner' => ['width' => 1920, 'height' => 720, 'max_kb' => 500],
    ];

    public function __construct(protected ImageOptimizer $images) {}

    /**
     * @param  array  $profile  name, phone, email, address, city, description
     * @param  array  $settings  primary_color, secondary_color, tagline, opening_hours, facilities, social
     */
    public function update(Gym $gym, array $profile, array $settings, ?UploadedFile $logo = null, ?UploadedFile $banner = null): Gym
    {
        $gym->fill($profile);

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
            $gym->logo_path = $this->storeOptimized($gym, $logo, 'logo', $gym->logo_path);
        }

        if ($banner) {
            $gym->banner_path = $this->storeOptimized($gym, $banner, 'banner', $gym->banner_path);
        }

        $gym->save();

        return $gym;
    }

    /**
     * Compress a logo / banner to its size budget and store it; the previous file is deleted.
     * Also used to shrink pictures uploaded before compression existed (gymai:optimize-branding).
     */
    public function storeOptimized(Gym $gym, UploadedFile|string $source, string $kind, ?string $previous = null): string
    {
        $rules = self::IMAGE_RULES[$kind];
        $image = $this->images->optimize($source, $rules['width'], $rules['height'], $rules['max_kb'] * 1024, $kind);

        $path = "gyms/{$gym->id}/branding/{$kind}-".Str::lower(Str::random(10)).'.'.$image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
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
