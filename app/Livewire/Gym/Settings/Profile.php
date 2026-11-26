<?php

namespace App\Livewire\Gym\Settings;

use App\Models\City;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\Locality;
use App\Services\GymProfileService;
use App\Services\QrCodeService;
use App\Support\Color;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.gym')]
#[Title('Gym profile')]
class Profile extends Component
{
    use WithFileUploads;

    #[Url(except: 'profile')]
    public string $tab = 'profile';

    /** @var array<string, string> */
    public array $profile = [];

    /** @var array<string, mixed> */
    public array $settings = [];

    public string $facilities = '';

    public $logo = null;

    public $banner = null;

    /** @var array<int, mixed> */
    public array $photos = [];

    public string $photoCaption = '';

    /** @var array<string, array{primary: string, secondary: string}> Dashboard colours per area (admin, member). */
    public array $theme = [];

    /** Ready-made colour pairs offered beside the pickers: [label, primary, secondary]. */
    public const THEME_PRESETS = [
        ['Electric Orange', '#ff6b00', '#0d0d0d'],
        ['Royal Blue', '#3b82f6', '#0b1220'],
        ['Emerald', '#059669', '#06140f'],
        ['Crimson', '#e11d48', '#140609'],
        ['Violet', '#8b5cf6', '#100b1f'],
        ['Gold', '#b8860b', '#141006'],
    ];

    public function mount(): void
    {
        $gym = $this->gym();
        $this->profile = [
            'name' => $gym->name, 'phone' => (string) $gym->phone, 'email' => (string) $gym->email,
            'address' => (string) $gym->address, 'map_url' => (string) $gym->map_url, 'city_id' => (string) $gym->city_id, 'locality' => (string) $gym->locality?->name, 'description' => (string) $gym->description,
        ];
        $all = $gym->allSettings();
        $this->settings = [
            'primary_color' => $gym->primaryColor(),
            'secondary_color' => $gym->secondaryColor(),
            'tagline' => (string) $all['tagline'],
            'opening_hours' => $all['opening_hours'],
            'social' => array_map(fn ($v) => (string) $v, $all['social']),
        ];
        $this->facilities = implode("\n", $all['facilities']);
        $this->theme = collect(Gym::DASHBOARD_AREAS)->map(fn ($label, $area) => collect($gym->dashboardColors($area))->only(['primary', 'secondary'])->all())->all();
    }

    public function applyThemePreset(string $area, int $preset): void
    {
        abort_unless(array_key_exists($area, Gym::DASHBOARD_AREAS) && isset(self::THEME_PRESETS[$preset]), 404);

        [, $primary, $secondary] = self::THEME_PRESETS[$preset];
        $this->theme[$area] = ['primary' => $primary, 'secondary' => $secondary];
        $this->resetValidation("theme.{$area}.*");
    }

    public function saveTheme(string $area, GymProfileService $service): void
    {
        abort_unless(array_key_exists($area, Gym::DASHBOARD_AREAS), 404);

        $this->validate([
            "theme.{$area}.primary" => ['bail', 'required', 'regex:/^#[0-9a-fA-F]{6}$/', function (string $attribute, mixed $value, \Closure $fail) {
                // Buttons put white text on the primary colour, and it must also stand out on the dark theme.
                if (Color::contrast($value, '#ffffff') < 2.5) {
                    $fail('Choose a darker or stronger primary colour — white button text is hard to read on it.');
                } elseif (Color::contrast($value, '#111111') < 2) {
                    $fail('Choose a brighter primary colour — it disappears on the dark background.');
                }
            }],
            "theme.{$area}.secondary" => ['bail', 'required', 'regex:/^#[0-9a-fA-F]{6}$/', function (string $attribute, mixed $value, \Closure $fail) {
                // Menu text is light grey, so the navigation background has to stay dark.
                if (Color::contrast($value, '#a3a3a3') < 3) {
                    $fail('Choose a darker secondary colour — the menu text needs a dark background.');
                }
            }],
        ], [], ["theme.{$area}.primary" => 'primary colour', "theme.{$area}.secondary" => 'secondary colour']);

        $service->updateDashboardTheme($this->gym(), $area, $this->theme[$area]);

        $this->confirmTheme($area, 'colours saved.');
    }

    public function resetTheme(string $area, GymProfileService $service): void
    {
        abort_unless(array_key_exists($area, Gym::DASHBOARD_AREAS), 404);

        $service->updateDashboardTheme($this->gym(), $area, null);
        $this->theme[$area] = ['primary' => Gym::DASHBOARD_THEME_DEFAULTS['primary'], 'secondary' => Gym::DASHBOARD_THEME_DEFAULTS['secondary']];
        $this->resetValidation("theme.{$area}.*");

        $this->confirmTheme($area, 'colours reset to the Fitnix theme.');
    }

    /** Admin colours repaint this very panel, so reload it; member colours show in the member app. */
    protected function confirmTheme(string $area, string $message): void
    {
        $message = Gym::DASHBOARD_AREAS[$area].' '.$message;

        if ($area === 'admin') {
            session()->flash('success', $message);
            $this->redirectRoute('gym.settings', ['tab' => 'theme']);

            return;
        }

        $this->dispatch('notify', message: $message);
    }

    protected function gym(): Gym
    {
        return current_gym();
    }

    public function save(GymProfileService $service): void
    {
        $this->validate([
            'profile.name' => 'required|string|max:120',
            'profile.phone' => 'nullable|string|max:20',
            'profile.email' => 'nullable|email|max:255',
            'profile.address' => 'nullable|string|max:255',
            'profile.map_url' => 'nullable|url:http,https|max:500',
            // An active city, or the gym's current one even if the Super Admin has since switched it off.
            'profile.city_id' => ['required', Rule::exists('cities', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $this->gym()->city_id))],
            'profile.locality' => 'nullable|string|max:80',
            'profile.description' => 'nullable|string|max:3000',
            'settings.primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.tagline' => 'nullable|string|max:120',
            'settings.opening_hours.*.open' => 'nullable|date_format:H:i',
            'settings.opening_hours.*.close' => 'nullable|date_format:H:i',
            'settings.opening_hours.*.closed' => 'boolean',
            'settings.social.website' => 'nullable|url|max:255',
            'settings.social.instagram' => 'nullable|url|max:255',
            'settings.social.facebook' => 'nullable|url|max:255',
            'settings.social.youtube' => 'nullable|url|max:255',
            'settings.social.whatsapp' => 'nullable|string|max:20',
            'facilities' => 'nullable|string|max:2000',
            // Originals up to 10 MB: we resize and compress them (logo ≤ 70 KB, banner ≤ 500 KB).
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ], [
            'logo.max' => 'The logo file must be smaller than 10 MB (we compress it for you).',
            'banner.max' => 'The banner file must be smaller than 10 MB (we compress it for you).',
        ], ['profile.name' => 'gym name', 'profile.city_id' => 'city']);

        $settings = $this->settings;
        $settings['facilities'] = preg_split('/\r?\n/', $this->facilities);
        $settings['social'] = array_map(fn ($v) => $v ?: null, $settings['social']);

        $service->update($this->gym(), array_map(fn ($v) => $v === '' ? null : $v, $this->profile), $settings, $this->logo, $this->banner);
        $this->reset('logo', 'banner');

        $this->dispatch('notify', message: 'Gym profile saved.');
    }

    public function removeLogo(GymProfileService $service): void
    {
        $service->removeLogo($this->gym());
    }

    public function removeBanner(GymProfileService $service): void
    {
        $service->removeBanner($this->gym());
    }

    public function uploadPhotos(GymProfileService $service): void
    {
        $this->validate(['photos' => 'required|array|max:10', 'photos.*' => 'image|max:4096', 'photoCaption' => 'nullable|string|max:120']);

        if (GymPhoto::count() + count($this->photos) > 30) {
            $this->addError('photos', 'You can store up to 30 photos.');

            return;
        }

        foreach ($this->photos as $photo) {
            $service->addPhoto($this->gym(), $photo, $this->photoCaption ?: null);
        }

        $this->reset('photos', 'photoCaption');
        $this->dispatch('notify', message: 'Photos uploaded.');
    }

    public function deletePhoto(int $id, GymProfileService $service): void
    {
        $service->deletePhoto(GymPhoto::findOrFail($id));
    }

    public function regenerateJoinLink(GymProfileService $service): void
    {
        $service->regenerateJoinToken($this->gym());
        $this->dispatch('notify', message: 'New QR link generated. Old printed QR codes no longer work.');
    }

    public function render(QrCodeService $qr): View
    {
        $gym = $this->gym()->fresh();

        return view('livewire.gym.settings.profile', [
            'gym' => $gym,
            'gymPhotos' => GymPhoto::orderBy('sort_order')->get(),
            'cities' => City::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $gym->city_id))->orderBy('name')->get(['id', 'name', 'state']),
            'joinQr' => $qr->svg($gym->joinUrl(), 220),
            'localities' => Locality::where('city_id', (int) $this->profile['city_id'])->orderBy('name')->pluck('name'),
            'completeness' => $gym->profileCompleteness(),
        ]);
    }
}
