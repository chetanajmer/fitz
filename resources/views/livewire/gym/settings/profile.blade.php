<div>
    <x-ui.page-header title="Gym profile" subtitle="Branding and details shown on your public page.">
        <x-slot:actions><a href="{{ $gym->publicUrl() }}" target="_blank" class="btn-secondary"><x-ui.icon name="globe" class="size-4" /> View public page</a></x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
        @foreach (['profile' => 'Profile & branding', 'hours' => 'Hours & facilities', 'photos' => 'Photos', 'qr' => 'QR registration'] as $key => $label)
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
                            <x-ui.field label="City" name="profile.city"><input wire:model="profile.city" class="input"></x-ui.field>
                            <x-ui.field label="Description" name="profile.description" class="sm:col-span-2"><textarea wire:model="profile.description" rows="4" class="input" placeholder="Tell visitors about your gym…"></textarea></x-ui.field>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="card card-body space-y-4">
                            <h2 class="card-title">Logo</h2>
                            <div class="flex items-center gap-4">
                                @if ($logo)
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
                            @error('logo') <p class="input-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="card card-body space-y-3">
                            <h2 class="card-title">Banner image</h2>
                            <p class="hint !mt-0">Shown at the top of your public page and member app. Wide image, e.g. 1600×600.</p>
                            @if ($banner)
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
                            <div class="h-10 rounded-lg" style="background: linear-gradient(90deg, {{ preg_match('/^#[0-9a-fA-F]{6}$/', $settings['primary_color']) ? $settings['primary_color'] : '#4f46e5' }}, {{ preg_match('/^#[0-9a-fA-F]{6}$/', $settings['secondary_color']) ? $settings['secondary_color'] : '#0ea5e9' }})"></div>
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
</div>
