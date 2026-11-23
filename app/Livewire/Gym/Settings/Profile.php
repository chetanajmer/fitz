<?php

namespace App\Livewire\Gym\Settings;

use App\Models\City;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Services\GymProfileService;
use App\Services\QrCodeService;
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

    public function mount(): void
    {
        $gym = $this->gym();
        $this->profile = [
            'name' => $gym->name, 'phone' => (string) $gym->phone, 'email' => (string) $gym->email,
            'address' => (string) $gym->address, 'city_id' => (string) $gym->city_id, 'description' => (string) $gym->description,
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
            // An active city, or the gym's current one even if the Super Admin has since switched it off.
            'profile.city_id' => ['required', Rule::exists('cities', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $this->gym()->city_id))],
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
            'logo' => 'nullable|image|max:2048',
            'banner' => 'nullable|image|max:4096',
        ], [], ['profile.name' => 'gym name', 'profile.city_id' => 'city']);

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
        ]);
    }
}
