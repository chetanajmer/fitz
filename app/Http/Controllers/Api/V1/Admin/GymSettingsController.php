<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Livewire\Gym\Settings\Profile;
use App\Models\City;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\Locality;
use App\Services\CheckInCodeService;
use App\Services\GymProfileService;
use App\Services\StreakService;
use App\Support\Color;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gym profile, branding and gym-wide settings for the staff app (gym admins: permission gym.settings).
 * Mirrors the web panel's Settings → Gym profile, QR codes, streak rules and weigh-in interval, through
 * the same GymProfileService, so a change made in the app or on the web looks the same everywhere.
 *
 * Every endpoint answers with the full settings object (see settingsData()), so the app can simply
 * replace its copy after each save.
 */
class GymSettingsController extends AdminController
{
    /** Gym photos kept per gym (same limit as the web panel). */
    public const PHOTO_LIMIT = 30;

    public function __construct(protected GymProfileService $profiles, protected CheckInCodeService $checkInCodes) {}

    /** GET /admin/gym */
    public function show(): JsonResponse
    {
        return $this->success($this->settingsData($this->gym()), 'Gym settings.');
    }

    /**
     * PUT /admin/gym — profile, public branding colours, tagline, opening hours, facilities and social links.
     * Partial: fields left out keep their current value. "social" and "opening_hours" merge over the current ones.
     */
    public function update(Request $request): JsonResponse
    {
        $gym = $this->gym();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'map_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            // An active city, or the gym's current one even if the Super Admin has since switched it off.
            'city_id' => ['sometimes', 'required', 'integer', Rule::exists('cities', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $gym->city_id))],
            'locality' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'facilities' => ['sometimes', 'array', 'max:50'],
            'facilities.*' => ['nullable', 'string', 'max:80'],
            'social' => ['sometimes', 'array'],
            'social.website' => ['nullable', 'url', 'max:255'],
            'social.instagram' => ['nullable', 'url', 'max:255'],
            'social.facebook' => ['nullable', 'url', 'max:255'],
            'social.youtube' => ['nullable', 'url', 'max:255'],
            'social.whatsapp' => ['nullable', 'string', 'max:20'],
            'opening_hours' => ['sometimes', 'array', 'max:7'],
            'opening_hours.*.day' => ['required', Rule::in(array_keys(Gym::DEFAULT_SETTINGS['opening_hours']))],
            'opening_hours.*.open' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.close' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closed' => ['sometimes', 'boolean'],
        ], [], ['city_id' => 'city']);

        $blankToNull = fn (mixed $value) => is_string($value) && trim($value) === '' ? null : $value;

        $profile = collect($data)->only(['name', 'phone', 'email', 'address', 'map_url', 'city_id', 'locality', 'description'])
            ->map($blankToNull)->all();

        $current = $gym->allSettings();
        $hours = $current['opening_hours'];
        foreach ($data['opening_hours'] ?? [] as $day) {
            $hours[$day['day']] = [
                'open' => $day['open'] ?? $hours[$day['day']]['open'],
                'close' => $day['close'] ?? $hours[$day['day']]['close'],
                'closed' => (bool) ($day['closed'] ?? $hours[$day['day']]['closed']),
            ];
        }

        $settings = [
            'primary_color' => isset($data['primary_color']) ? strtolower($data['primary_color']) : $gym->primaryColor(),
            'secondary_color' => isset($data['secondary_color']) ? strtolower($data['secondary_color']) : $gym->secondaryColor(),
            'tagline' => array_key_exists('tagline', $data) ? $blankToNull($data['tagline']) : $current['tagline'],
            'opening_hours' => $hours,
            'facilities' => array_key_exists('facilities', $data) ? array_map(fn ($f) => (string) $f, $data['facilities']) : $current['facilities'],
            'social' => array_map($blankToNull, array_merge($current['social'], array_intersect_key($data['social'] ?? [], Gym::DEFAULT_SETTINGS['social']))),
        ];

        $this->profiles->update($gym, $profile, $settings);

        return $this->success($this->settingsData($gym->fresh()), 'Gym profile saved.');
    }

    /** POST /admin/gym/logo (multipart: logo) — resized to 512×512 and compressed to ≤ 70 KB. */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240']], [
            'logo.max' => 'The logo file must be smaller than 10 MB (we compress it for you).',
        ]);

        $gym = $this->gym();
        $gym->logo_path = $this->profiles->storeOptimized($gym, $request->file('logo'), 'logo', $gym->logo_path);
        $gym->save();

        return $this->success($this->settingsData($gym->fresh()), 'Logo updated.');
    }

    /** DELETE /admin/gym/logo */
    public function deleteLogo(): JsonResponse
    {
        $gym = $this->gym();
        $this->profiles->removeLogo($gym);

        return $this->success($this->settingsData($gym->fresh()), 'Logo removed.');
    }

    /** POST /admin/gym/banner (multipart: banner) — resized to 1920×720 and compressed to ≤ 500 KB. */
    public function uploadBanner(Request $request): JsonResponse
    {
        $request->validate(['banner' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']], [
            'banner.max' => 'The banner file must be smaller than 10 MB (we compress it for you).',
        ]);

        $gym = $this->gym();
        $gym->banner_path = $this->profiles->storeOptimized($gym, $request->file('banner'), 'banner', $gym->banner_path);
        $gym->save();

        return $this->success($this->settingsData($gym->fresh()), 'Banner updated.');
    }

    /** DELETE /admin/gym/banner */
    public function deleteBanner(): JsonResponse
    {
        $gym = $this->gym();
        $this->profiles->removeBanner($gym);

        return $this->success($this->settingsData($gym->fresh()), 'Banner removed.');
    }

    /** POST /admin/gym/photos (multipart: photos[] up to 10, 4 MB each; caption optional) */
    public function uploadPhotos(Request $request): JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:120'],
        ]);

        if (GymPhoto::count() + count($request->file('photos')) > self::PHOTO_LIMIT) {
            throw new ApiException('You can store up to '.self::PHOTO_LIMIT.' photos.', 'PHOTO_LIMIT_REACHED', 422, ['limit' => self::PHOTO_LIMIT]);
        }

        $gym = $this->gym();
        foreach ($request->file('photos') as $photo) {
            $this->profiles->addPhoto($gym, $photo, $request->input('caption') ?: null);
        }

        return $this->success($this->settingsData($gym->fresh()), 'Photos uploaded.', 201);
    }

    /** DELETE /admin/gym/photos/{photoId} */
    public function deletePhoto(int $photoId): JsonResponse
    {
        $photo = GymPhoto::find($photoId) ?? throw new ApiException('Photo not found.', 'NOT_FOUND', 404);
        $this->profiles->deletePhoto($photo);

        return $this->success($this->settingsData($this->gym()->fresh()), 'Photo deleted.');
    }

    /**
     * PUT /admin/gym/theme/{area} { primary, secondary } — colours of the admin panel / staff app ("admin")
     * or the member app and portal ("member"). Same readability checks as the web panel.
     */
    public function updateTheme(Request $request, string $area): JsonResponse
    {
        $request->validate([
            'primary' => ['bail', 'required', 'regex:/^#[0-9a-fA-F]{6}$/', function (string $attribute, mixed $value, Closure $fail) {
                // Buttons put white text on the primary colour, and it must also stand out on the dark theme.
                if (Color::contrast($value, '#ffffff') < 2.5) {
                    $fail('Choose a darker or stronger primary colour — white button text is hard to read on it.');
                } elseif (Color::contrast($value, '#111111') < 2) {
                    $fail('Choose a brighter primary colour — it disappears on the dark background.');
                }
            }],
            'secondary' => ['bail', 'required', 'regex:/^#[0-9a-fA-F]{6}$/', function (string $attribute, mixed $value, Closure $fail) {
                // Menu text is light grey, so the navigation background has to stay dark.
                if (Color::contrast($value, '#a3a3a3') < 3) {
                    $fail('Choose a darker secondary colour — the menu text needs a dark background.');
                }
            }],
        ], [], ['primary' => 'primary colour', 'secondary' => 'secondary colour']);

        $gym = $this->profiles->updateDashboardTheme($this->gym(), $area, $request->only(['primary', 'secondary']));

        return $this->success($this->settingsData($gym->fresh()), Gym::DASHBOARD_AREAS[$area].' colours saved.');
    }

    /** DELETE /admin/gym/theme/{area} — back to the Fitnix theme. */
    public function resetTheme(string $area): JsonResponse
    {
        $gym = $this->profiles->updateDashboardTheme($this->gym(), $area, null);

        return $this->success($this->settingsData($gym->fresh()), Gym::DASHBOARD_AREAS[$area].' colours reset to the Fitnix theme.');
    }

    /** PUT /admin/gym/attendance-settings { member_self_checkin?, qr_checkin?, checkin_hours_only? } — member app check-in rules. Partial. */
    public function attendanceSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_self_checkin' => ['sometimes', 'boolean'],
            'qr_checkin' => ['sometimes', 'boolean'],
            'checkin_hours_only' => ['sometimes', 'boolean'],
        ]);

        $gym = $this->gym();
        $settings = $gym->settings ?? [];
        $settings['attendance'] = array_merge($settings['attendance'] ?? [], array_map(fn ($v) => (bool) $v, $data));
        $gym->settings = $settings;
        $gym->save();

        return $this->success($this->settingsData($gym->fresh()), 'Check-in settings saved.');
    }

    /**
     * PUT /admin/gym/rules { streak?: { grace_days?, skip_closed_days?, milestones? }, weigh_in_every_days? } — partial.
     * When a streak rule changes, every member's streak is recalculated (data.streaks_recalculated).
     */
    public function updateRules(Request $request, StreakService $streaks): JsonResponse
    {
        $data = $request->validate([
            'streak' => ['sometimes', 'array'],
            'streak.grace_days' => ['sometimes', 'integer', 'min:0', 'max:7'],
            'streak.skip_closed_days' => ['sometimes', 'boolean'],
            'streak.milestones' => ['sometimes', 'array', 'min:1', 'max:20'],
            'streak.milestones.*' => ['integer', 'min:1', 'max:3650'],
            'weigh_in_every_days' => ['sometimes', 'integer', Rule::in([7, 15, 30])],
        ]);

        $gym = $this->gym();
        $settings = $gym->settings ?? [];

        $streakChanged = isset($data['streak']);
        if ($streakChanged) {
            $rules = $gym->streakRules();
            $settings['streak'] = [
                'grace_days' => (int) ($data['streak']['grace_days'] ?? $rules['grace_days']),
                'skip_closed_days' => (bool) ($data['streak']['skip_closed_days'] ?? $rules['skip_closed_days']),
                'milestones' => isset($data['streak']['milestones'])
                    ? collect($data['streak']['milestones'])->map(fn ($m) => (int) $m)->unique()->sort()->values()->all()
                    : $rules['milestones'],
            ];
        }

        if (isset($data['weigh_in_every_days'])) {
            $settings['progress'] = ['weigh_in_every_days' => (int) $data['weigh_in_every_days']];
        }

        $gym->settings = $settings;
        $gym->save();

        $recalculated = null;
        if ($streakChanged) {
            $streaks->forgetRules($gym->id);
            $recalculated = $streaks->refreshGym($gym);
        }

        return $this->success(
            $this->settingsData($gym->fresh()) + ['streaks_recalculated' => $recalculated],
            $recalculated === null ? 'Settings saved.' : "Settings saved. {$recalculated} member streaks recalculated.",
        );
    }

    /** GET /admin/gym/qr — join, self-registration and check-in QR links (draw the QR codes in the app). */
    public function qr(): JsonResponse
    {
        return $this->success($this->settingsData($this->gym())['links'], 'QR codes.');
    }

    /** PUT /admin/gym/qr/check-in-mode { mode: printed|screen } — which check-in QR members must scan. */
    public function checkInQrMode(Request $request): JsonResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(array_keys(CheckInCodeService::MODES))]]);

        $gym = $this->profiles->setCheckinQrMode($this->gym(), $data['mode']);

        return $this->success($this->settingsData($gym->fresh()), 'Members now scan the '.lcfirst(CheckInCodeService::MODES[$data['mode']]).'.');
    }

    /**
     * POST /admin/gym/qr/regenerate { which: join|register|check_in }.
     * The old QR code / link stops working at once (printed copies must be replaced).
     */
    public function regenerateQr(Request $request): JsonResponse
    {
        $data = $request->validate(['which' => ['required', Rule::in(['join', 'register', 'check_in'])]]);
        $gym = $this->gym();

        match ($data['which']) {
            'join' => $this->profiles->regenerateJoinToken($gym),
            'register' => $this->profiles->regenerateRegisterToken($gym),
            'check_in' => $this->profiles->regenerateCheckinToken($gym),
        };

        return $this->success($this->settingsData($gym->fresh()), 'New link generated. Old QR codes no longer work.');
    }

    /** GET /admin/gym/cities — cities a gym can pick (active ones, plus the gym's current city). */
    public function cities(): JsonResponse
    {
        $gym = $this->gym();
        $cities = City::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $gym->city_id))->orderBy('name')->get(['id', 'name', 'state']);

        return $this->success([
            'cities' => $cities->map(fn (City $c) => ['id' => $c->id, 'name' => $c->name, 'state' => $c->state])->values(),
        ], 'Cities.');
    }

    /** GET /admin/gym/cities/{cityId}/localities — known areas of a city (a new name can also be typed). */
    public function localities(int $cityId): JsonResponse
    {
        $gym = $this->gym();
        $city = City::where('id', $cityId)->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $gym->city_id))->first()
            ?? throw new ApiException('City not found.', 'NOT_FOUND', 404);

        return $this->success([
            'city' => ['id' => $city->id, 'name' => $city->name],
            'localities' => Locality::where('city_id', $city->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Locality $l) => ['id' => $l->id, 'name' => $l->name])->values(),
        ], 'Localities.');
    }

    protected function gym(): Gym
    {
        return current_gym();
    }

    /**
     * The full settings object returned by every endpoint here.
     *
     * @return array<string, mixed>
     */
    protected function settingsData(Gym $gym): array
    {
        $gym->loadMissing(['locality', 'cityRecord']);
        $settings = $gym->allSettings();
        $rules = $gym->streakRules();

        return [
            'gym' => [
                'id' => $gym->id,
                'slug' => $gym->slug,
                'status' => $gym->status?->value,
                'public_url' => $gym->publicUrl(),
                'updated_at' => $this->iso($gym->updated_at),
            ],
            'profile' => [
                'name' => $gym->name,
                'phone' => $gym->phone,
                'email' => $gym->email,
                'address' => $gym->address,
                'map_url' => $gym->map_url,
                'city' => $gym->cityRecord ? ['id' => $gym->cityRecord->id, 'name' => $gym->cityRecord->name] : null,
                'locality' => $gym->locality?->name,
                'description' => $gym->description,
            ],
            'branding' => [
                'tagline' => $settings['tagline'],
                'logo_url' => $gym->logoUrl(),
                'banner_url' => $gym->bannerUrl(),
                'primary_color' => $gym->primaryColor(),
                'secondary_color' => $gym->secondaryColor(),
                'image_rules' => GymProfileService::IMAGE_RULES,
            ],
            'opening_hours' => collect($settings['opening_hours'])->map(fn (array $h, string $day) => [
                'day' => $day,
                'open' => $h['open'],
                'close' => $h['close'],
                'closed' => (bool) $h['closed'],
            ])->values()->all(),
            'facilities' => array_values($settings['facilities']),
            'social' => collect(Gym::DEFAULT_SETTINGS['social'])->keys()->mapWithKeys(fn (string $key) => [$key => $settings['social'][$key] ?? null])->all(),
            'photos' => GymPhoto::orderBy('sort_order')->get()
                ->map(fn (GymPhoto $p) => ['id' => $p->id, 'url' => $p->url(), 'caption' => $p->caption])->values()->all(),
            'photos_limit' => self::PHOTO_LIMIT,
            'dashboard_themes' => [
                'admin' => $gym->dashboardColors('admin'),
                'member' => $gym->dashboardColors('member'),
                'defaults' => Gym::DASHBOARD_THEME_DEFAULTS,
                'presets' => collect(Profile::THEME_PRESETS)->map(fn (array $p) => ['name' => $p[0], 'primary' => $p[1], 'secondary' => $p[2]])->all(),
            ],
            'rules' => [
                'attendance' => [
                    'member_self_checkin' => $gym->allowsSelfCheckIn(),
                    'qr_checkin' => $gym->allowsQrCheckIn(),
                    'checkin_hours_only' => $gym->limitsCheckInToOpeningHours(),
                ],
                'streak' => [
                    'grace_days' => $rules['grace_days'],
                    'skip_closed_days' => $rules['skip_closed_days'],
                    'milestones' => $rules['milestones'],
                ],
                'weigh_in_every_days' => $gym->weighInEveryDays(),
            ],
            'links' => [
                'join_url' => $gym->joinUrl(),
                'register_url' => $gym->register_token ? $gym->registerUrl() : null,
                'check_in_qr_mode' => $this->checkInCodes->mode($gym),
                'check_in_qr_url' => $gym->checkin_token ? $this->checkInCodes->url($this->checkInCodes->printedCode($gym)) : null,
            ],
            'completeness' => $gym->profileCompleteness(),
        ];
    }
}
