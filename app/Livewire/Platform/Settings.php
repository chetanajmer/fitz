<?php

namespace App\Livewire\Platform;

use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.platform')]
#[Title('Platform settings')]
class Settings extends Component
{
    /** @var array<string, string> */
    public array $settings = [];

    public function mount(): void
    {
        foreach (array_keys(PlatformSetting::DEFAULTS) as $key) {
            $this->settings[$key] = (string) PlatformSetting::get($key);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'settings.platform_name' => 'required|string|max:80',
            'settings.support_email' => 'required|email|max:255',
            'settings.support_phone' => 'nullable|string|max:20',
            'settings.invoice_prefix' => 'required|alpha_num|max:12',
            'settings.invoice_due_days' => 'required|integer|min:1|max:90',
            'settings.company_address' => 'nullable|string|max:500',
            'settings.gst_number' => 'nullable|string|max:20',
            'settings.allow_registrations' => 'required|in:0,1',
            'settings.android_latest_version' => ['required', 'regex:/^\d+(\.\d+){0,3}$/'],
            'settings.android_min_version' => ['required', 'regex:/^\d+(\.\d+){0,3}$/'],
            'settings.android_store_url' => 'nullable|url|max:255',
            'settings.android_release_notes' => 'nullable|string|max:1000',
            'settings.ios_latest_version' => ['required', 'regex:/^\d+(\.\d+){0,3}$/'],
            'settings.ios_min_version' => ['required', 'regex:/^\d+(\.\d+){0,3}$/'],
            'settings.ios_store_url' => 'nullable|url|max:255',
            'settings.ios_release_notes' => 'nullable|string|max:1000',
            'settings.app_maintenance_mode' => 'required|in:0,1',
            'settings.app_maintenance_message' => 'nullable|string|max:255',
        ], ['regex' => 'Use a version like 1.2.3.'])['settings'];

        foreach (['android', 'ios'] as $platform) {
            if (version_compare($data["{$platform}_min_version"], $data["{$platform}_latest_version"], '>')) {
                $this->addError("settings.{$platform}_min_version", 'Minimum version cannot be higher than the latest version.');

                return;
            }
        }

        PlatformSetting::put($data);
        $this->dispatch('notify', message: 'Settings saved.');
    }

    public function render(): View
    {
        return view('livewire.platform.settings');
    }
}
