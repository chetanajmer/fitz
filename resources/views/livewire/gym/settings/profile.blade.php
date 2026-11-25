@php
    $fileKb = fn (?string $path) => $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)
        ? (int) ceil(\Illuminate\Support\Facades\Storage::disk('public')->size($path) / 1024) : null;
@endphp
<div>
    <x-ui.page-header title="Gym profile" subtitle="Branding and details shown on your public page.">
        <x-slot:actions><a href="{{ $gym->publicUrl() }}" target="_blank" class="btn-secondary"><x-ui.icon name="globe" class="size-4" /> View public page</a></x-slot:actions>
    </x-ui.page-header>

    @if ($completeness['percent'] < 100)
        <div class="card card-body mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold">Profile {{ $completeness['percent'] }}% complete</p>
                <p class="text-xs text-gray-500">New members see this profile when they choose a gym to join.</p>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $completeness['percent'] }}%"></div></div>
            <p class="mt-2 text-sm text-gray-600">Still missing: {{ implode(', ', $completeness['missing']) }}.</p>
        </div>
    @endif

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
        @foreach (['profile' => 'Profile & branding', 'hours' => 'Hours & facilities', 'photos' => 'Photos', 'qr' => 'QR registration', 'theme' => 'Dashboard colours'] as $key => $label)
            <button wire:key="tab-{{ $key }}" wire:click="$set('tab', '{{ $key }}')" class="tab {{ $tab === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if (in_array($tab, ['profile', 'hours']))
        <form wire:submit="save" class="space-y-6">
            @if ($tab === 'profile')
                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="card lg:col-span-2">
                        <div class="card-header"><h2 class="card-title">Gym details</h2></div>
                        <div class="card-body grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Gym name" name="profile.name" required><input wire:model="profile.name" class="input"></x-ui.field>
                            <x-ui.field label="Tagline" name="settings.tagline"><input wire:model="settings.tagline" class="input" placeholder="Train hard. Live strong."></x-ui.field>
                            <x-ui.field label="Phone" name="profile.phone"><input wire:model="profile.phone" class="input"></x-ui.field>
                            <x-ui.field label="Email" name="profile.email"><input wire:model="profile.email" type="email" class="input"></x-ui.field>
                            <x-ui.field label="Address" name="profile.address"><input wire:model="profile.address" class="input"></x-ui.field>
                            <x-ui.field label="Google Maps link" name="profile.map_url" hint="Paste your gym's location link from Google Maps."><input wire:model="profile.map_url" type="url" class="input" placeholder="https://maps.app.goo.gl/…"></x-ui.field>
                            <x-ui.field label="City" name="profile.city_id" required hint="Missing your city? Ask Gymai support to add it.">
                                <select wire:model.live="profile.city_id" class="input">
                                    <option value="">Select city</option>
                                    @foreach ($cities as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}{{ $c->state ? ', '.$c->state : '' }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field label="Locality / area" name="profile.locality" hint="Members filter gyms by area when they join. Pick one from the list or type a new one.">
                                <input wire:model="profile.locality" list="gym-localities" class="input" placeholder="e.g. Jagatpura" autocomplete="off">
                                <datalist id="gym-localities">@foreach ($localities as $l)<option value="{{ $l }}">@endforeach</datalist>
                            </x-ui.field>
                            <x-ui.field label="Description" name="profile.description" class="sm:col-span-2"><textarea wire:model="profile.description" rows="4" class="input" placeholder="Tell visitors about your gym…"></textarea></x-ui.field>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="card card-body space-y-4">
                            <h2 class="card-title">Logo</h2>
                            <div class="flex items-center gap-4">
                                @if ($logo && $logo->isPreviewable())
                                    <img src="{{ $logo->temporaryUrl() }}" class="size-16 rounded-lg object-contain ring-1 ring-gray-200" alt="">
                                @elseif ($gym->logoUrl())
                                    <img src="{{ $gym->logoUrl() }}" class="size-16 rounded-lg object-contain ring-1 ring-gray-200" alt="">
                                @else
                                    <div class="flex size-16 items-center justify-center rounded-lg text-lg font-bold text-white" style="background: {{ $gym->primaryColor() }}">{{ $gym->initials() }}</div>
                                @endif
                                <div class="space-y-2">
                                    <input wire:model="logo" type="file" accept="image/*" class="text-sm">
                                    @if ($gym->logo_path)<button type="button" wire:click="removeLogo" class="text-xs text-red-600 hover:underline">Remove logo</button>@endif
                                </div>
                            </div>
                            <p class="hint">Square works best. We resize and compress it automatically (under 70 KB).@if ($gym->logo_path && ($logoKb = $fileKb($gym->logo_path))) Current file: {{ $logoKb }} KB.@endif</p>
                            @error('logo') <p class="input-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="card card-body space-y-3">
                            <h2 class="card-title">Banner image</h2>
                            <p class="hint !mt-0">Shown at the top of your public page and member app. Wide image, e.g. 1920×720. We resize and compress it automatically (under 500 KB).</p>
                            @if ($gym->banner_path && ($bannerKb = $fileKb($gym->banner_path)))<p class="hint !mt-0">Current file: {{ $bannerKb }} KB</p>@endif
                            @if ($banner && $banner->isPreviewable())
                                <img src="{{ $banner->temporaryUrl() }}" class="aspect-[8/3] w-full rounded-lg object-cover ring-1 ring-gray-200" alt="">
                            @elseif ($gym->bannerUrl())
                                <img src="{{ $gym->bannerUrl() }}" class="aspect-[8/3] w-full rounded-lg object-cover ring-1 ring-gray-200" alt="">
                            @else
                                <div class="aspect-[8/3] w-full rounded-lg" style="background: linear-gradient(135deg, {{ $gym->primaryColor() }}, {{ $gym->secondaryColor() }})"></div>
                            @endif
                            <div class="flex items-center justify-between gap-2">
                                <input wire:model="banner" type="file" accept="image/*" class="text-sm">
                                @if ($gym->banner_path)<button type="button" wire:click="removeBanner" class="text-xs text-red-600 hover:underline">Remove</button>@endif
                            </div>
                            <div wire:loading wire:target="banner" class="hint">Uploading…</div>
                            @error('banner') <p class="input-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="card card-body space-y-4">
                            <h2 class="card-title">Brand colours</h2>
                            @foreach (['primary_color' => 'Primary', 'secondary_color' => 'Secondary'] as $key => $label)
                                <x-ui.field :label="$label" :name="'settings.'.$key">
                                    <div class="flex items-center gap-2">
                                        <input wire:model.live="settings.{{ $key }}" type="color" class="h-10 w-14 cursor-pointer rounded border border-gray-300 p-1">
                                        <input wire:model.live.debounce.400ms="settings.{{ $key }}" class="input font-mono uppercase">
                                    </div>
                                </x-ui.field>
                            @endforeach
                            <div class="h-10 rounded-lg" style="background: linear-gradient(90deg, {{ preg_match('/^#[0-9a-fA-F]{6}$/', $settings['primary_color']) ? $settings['primary_color'] : '#ff6b00' }}, {{ preg_match('/^#[0-9a-fA-F]{6}$/', $settings['secondary_color']) ? $settings['secondary_color'] : '#111111' }})"></div>
                        </div>
                        <div class="card card-body space-y-3">
                            <h2 class="card-title">Social links</h2>
                            @foreach (['website' => 'Website URL', 'instagram' => 'Instagram URL', 'facebook' => 'Facebook URL', 'youtube' => 'YouTube URL', 'whatsapp' => 'WhatsApp number'] as $key => $label)
                                <x-ui.field :label="$label" :name="'settings.social.'.$key"><input wire:model="settings.social.{{ $key }}" class="input" placeholder="{{ $key === 'whatsapp' ? '919876543210' : 'https://' }}"></x-ui.field>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">Opening hours</h2></div>
                        <div class="card-body space-y-3">
                            @foreach ($settings['opening_hours'] as $day => $h)
                                <div wire:key="day-{{ $day }}" class="grid grid-cols-[6rem_1fr_1fr_auto] items-center gap-3">
                                    <span class="text-sm font-medium capitalize">{{ $day }}</span>
                                    <input wire:model="settings.opening_hours.{{ $day }}.open" type="time" class="input" @disabled($h['closed'])>
                                    <input wire:model="settings.opening_hours.{{ $day }}.close" type="time" class="input" @disabled($h['closed'])>
                                    <label class="flex items-center gap-1.5 text-xs text-gray-600"><input wire:model.live="settings.opening_hours.{{ $day }}.closed" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Closed</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h2 class="card-title">Facilities</h2></div>
                        <div class="card-body">
                            <x-ui.field name="facilities" hint="One per line, e.g. Cardio zone, Steam room, Parking, Personal training.">
                                <textarea wire:model="facilities" rows="10" class="input"></textarea>
                            </x-ui.field>
                        </div>
                    </div>
                </div>
            @endif
            <div class="flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled">Save changes</button></div>
        </form>
    @endif

    @if ($tab === 'photos')
        <div class="card mb-6">
            <form wire:submit="uploadPhotos" class="card-body flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-ui.field label="Add photos (max 10 at once)" name="photos" class="flex-1"><input wire:model="photos" type="file" accept="image/*" multiple class="text-sm"></x-ui.field>
                <x-ui.field label="Caption" name="photoCaption" class="flex-1"><input wire:model="photoCaption" class="input"></x-ui.field>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Upload</button>
            </form>
            @error('photos.*') <p class="input-error px-5 pb-4">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @forelse ($gymPhotos as $p)
                <div wire:key="ph-{{ $p->id }}" class="group relative overflow-hidden rounded-xl bg-gray-100">
                    <img src="{{ $p->url() }}" alt="{{ $p->caption }}" class="aspect-[4/3] w-full object-cover">
                    @if ($p->caption)<p class="absolute inset-x-0 bottom-0 bg-black/50 px-2 py-1 text-xs text-white">{{ $p->caption }}</p>@endif
                    <button wire:click="deletePhoto({{ $p->id }})" wire:confirm="Delete this photo?" class="absolute top-2 right-2 rounded-full bg-white/90 p-1 text-red-600 opacity-0 shadow group-hover:opacity-100"><x-ui.icon name="x" class="size-4" /></button>
                </div>
            @empty
                <div class="card col-span-full"><x-ui.empty message="No photos yet. Show off your gym!" icon="upload" /></div>
            @endforelse
        </div>
    @endif

    @if ($tab === 'qr')
        <div id="qr" class="grid gap-6 lg:grid-cols-2">
            <div class="card card-body text-center">
                <h2 class="card-title">QR registration code</h2>
                <p class="mt-1 text-sm text-gray-500">Print this at your front desk. Visitors who scan it fill a short form that creates an <strong>enquiry</strong> — not a member.</p>
                <div class="mx-auto mt-4 w-56 [&>svg]:h-auto [&>svg]:w-full">{!! $joinQr !!}</div>
                <div class="mt-4 flex justify-center gap-2">
                    <a href="{{ route('gym.settings.join-qr', ['download' => 1]) }}" class="btn-primary btn-sm">Download SVG</a>
                    <a href="{{ $gym->joinUrl() }}" target="_blank" class="btn-secondary btn-sm">Open form</a>
                </div>
            </div>
            <div class="card card-body space-y-4">
                <div>
                    <p class="label">Public profile</p>
                    <div class="flex gap-2" x-data="{ copied: false }">
                        <input readonly value="{{ $gym->publicUrl() }}" class="input font-mono text-xs">
                        <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText('{{ $gym->publicUrl() }}'); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button>
                    </div>
                </div>
                <div>
                    <p class="label">QR registration link</p>
                    <div class="flex gap-2" x-data="{ copied: false }">
                        <input readonly value="{{ $gym->joinUrl() }}" class="input font-mono text-xs">
                        <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText('{{ $gym->joinUrl() }}'); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button>
                    </div>
                </div>
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-medium">Link leaked or misused?</p>
                    <p class="mt-1">Generate a new secret link. Previously printed QR codes will stop working.</p>
                    <button wire:click="regenerateJoinLink" wire:confirm="Generate a new QR link? Old QR codes will stop working." class="btn-secondary btn-sm mt-3">Regenerate link</button>
                </div>
            </div>
        </div>
    @endif

    @if ($tab === 'theme')
        @php
            $presets = \App\Livewire\Gym\Settings\Profile::THEME_PRESETS;
            $hex = fn ($value, $fallback) => \App\Support\Color::isHex($value) ? $value : $fallback;
            $areaHints = [
                'admin' => 'Your gym panel: sidebar, buttons, active menu items, links and charts.',
                'member' => 'The member dashboard: top and bottom bars, buttons, links and highlights.',
            ];
        @endphp
        <div class="grid gap-6 xl:grid-cols-2">
            @foreach (\App\Models\Gym::DASHBOARD_AREAS as $area => $areaLabel)
                @php
                    $primary = $hex($theme[$area]['primary'] ?? null, \App\Models\Gym::DASHBOARD_THEME_DEFAULTS['primary']);
                    $secondary = $hex($theme[$area]['secondary'] ?? null, \App\Models\Gym::DASHBOARD_THEME_DEFAULTS['secondary']);
                    $saved = $gym->dashboardColors($area);
                @endphp
                <div wire:key="theme-{{ $area }}" class="card">
                    <div class="card-header flex items-center justify-between gap-3">
                        <div>
                            <h2 class="card-title">{{ $areaLabel }}</h2>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $areaHints[$area] }}</p>
                        </div>
                        <x-ui.badge :color="$saved['custom'] ? 'indigo' : 'gray'" class="shrink-0 whitespace-nowrap">{{ $saved['custom'] ? 'Custom' : 'Gymai default' }}</x-ui.badge>
                    </div>
                    <div class="card-body space-y-5">
                        {{-- Live preview --}}
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-ink-700" aria-hidden="true">
                            @if ($area === 'admin')
                                <div class="flex h-40">
                                    <div class="flex w-28 shrink-0 flex-col gap-1.5 p-3" style="background: {{ $secondary }}">
                                        <div class="mb-1 flex items-center gap-1.5"><span class="size-4 rounded" style="background: {{ $primary }}"></span><span class="h-1.5 w-12 rounded-full bg-white/70"></span></div>
                                        <span class="h-5 rounded-md" style="background: color-mix(in srgb, {{ $primary }} 18%, transparent); box-shadow: inset 2px 0 0 {{ $primary }}"></span>
                                        @foreach (range(1, 4) as $i)<span class="h-2 w-16 rounded-full bg-white/20"></span>@endforeach
                                    </div>
                                    <div class="flex flex-1 flex-col gap-2 bg-gray-50 p-3 dark:bg-ink-850">
                                        <div class="flex items-center justify-between"><span class="h-2 w-20 rounded-full bg-gray-300 dark:bg-white/20"></span><span class="rounded-md px-2 py-1 text-[10px] font-semibold text-white" style="background: {{ $primary }}">+ Add member</span></div>
                                        <div class="flex flex-1 items-end gap-1.5 rounded-lg bg-white p-2 dark:bg-ink-800">
                                            @foreach ([40, 65, 50, 85, 60, 95, 70] as $h)<span class="flex-1 rounded-sm" style="height: {{ $h }}%; background: {{ $loop->last ? $primary : 'color-mix(in srgb, '.$primary.' 45%, transparent)' }}"></span>@endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="mx-auto flex h-40 max-w-[15rem] flex-col">
                                    <div class="flex items-center gap-1.5 px-3 py-2" style="background: {{ $secondary }}"><span class="size-4 rounded" style="background: {{ $primary }}"></span><span class="h-1.5 w-16 rounded-full bg-white/70"></span></div>
                                    <div class="flex flex-1 flex-col gap-2 bg-gray-50 p-3 dark:bg-ink-850">
                                        <div class="rounded-lg p-2 text-[10px] font-semibold text-white" style="background: {{ $primary }}">Check in with QR</div>
                                        <div class="flex gap-1.5"><span class="h-8 flex-1 rounded-md bg-white dark:bg-ink-800"></span><span class="h-8 flex-1 rounded-md bg-white dark:bg-ink-800"></span></div>
                                        <span class="h-1.5 w-20 rounded-full" style="background: {{ $primary }}"></span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1 px-3 py-2" style="background: {{ $secondary }}">
                                        @foreach (range(0, 3) as $i)<span class="mx-auto size-3 rounded-full" style="background: {{ $i === 0 ? $primary : 'rgb(255 255 255 / 0.25)' }}"></span>@endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Presets --}}
                        <div>
                            <p class="label">Quick themes</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($presets as $i => [$label, $presetPrimary, $presetSecondary])
                                    @php
                                        $active = strcasecmp($presetPrimary, $primary) === 0 && strcasecmp($presetSecondary, $secondary) === 0;
                                    @endphp
                                    <button type="button" wire:click="applyThemePreset('{{ $area }}', {{ $i }})" title="{{ $label }}"
                                            class="flex items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-xs font-medium transition {{ $active ? 'border-brand-600 text-gray-900 dark:text-white' : 'border-gray-200 text-gray-600 hover:border-gray-400 dark:border-ink-700 dark:text-zinc-400' }}">
                                        <span class="flex size-6 overflow-hidden rounded-full ring-1 ring-black/10"><span class="w-1/2" style="background: {{ $presetPrimary }}"></span><span class="w-1/2" style="background: {{ $presetSecondary }}"></span></span>
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Pickers --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach (['primary' => ['Primary colour', 'Buttons, active items, links, charts'], 'secondary' => ['Secondary colour', 'Navigation background (keep it dark)']] as $key => [$label, $hint])
                                <x-ui.field :label="$label" :name="'theme.'.$area.'.'.$key" :hint="$hint">
                                    <div class="flex items-center gap-2">
                                        <input wire:model.live="theme.{{ $area }}.{{ $key }}" type="color" aria-label="{{ $label }}" class="h-10 w-14 shrink-0 cursor-pointer rounded border border-gray-300 p-1 dark:border-ink-700">
                                        <input wire:model.live.debounce.400ms="theme.{{ $area }}.{{ $key }}" class="input font-mono uppercase" maxlength="7">
                                    </div>
                                </x-ui.field>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-ink-700">
                            <button type="button" wire:click="resetTheme('{{ $area }}')" wire:confirm="Reset the {{ strtolower($areaLabel) }} to the Gymai colours?" class="text-sm font-medium text-gray-500 hover:text-brand-600 disabled:opacity-40" @disabled(! $saved['custom'])>Reset to default</button>
                            <button type="button" wire:click="saveTheme('{{ $area }}')" class="btn-primary" wire:loading.attr="disabled" wire:target="saveTheme('{{ $area }}')">Save {{ strtolower($areaLabel) }} colours</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-gray-500">These colours apply only to your gym's dashboards. Your public page, receipts and brand colours are set under <button type="button" wire:click="$set('tab', 'profile')" class="font-medium text-brand-600 hover:underline">Profile &amp; branding</button>.</p>
    @endif
</div>
