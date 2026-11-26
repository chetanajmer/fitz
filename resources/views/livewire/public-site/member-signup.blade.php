@php
    $steps = [1 => 'City', 2 => 'Gym', 3 => 'Your details', 4 => 'Verify phone'];
    // Left panel: one photo and message per step, cross-fading as the member moves through the form.
    $panels = [
        1 => [
            'image' => 'images/landing/hero-athlete.jpg',
            'position' => 'object-[50%_20%] lg:object-[45%_0%]',
            'tone' => 'brightness-105 contrast-110',
            'title' => 'Your fitness journey',
            'accent' => 'starts here.',
            'text' => 'Pick your city and find gyms near you that are on Fitnix.',
        ],
        2 => [
            'image' => 'images/register/step-2-gym.jpg',
            'position' => 'object-[55%_30%] lg:object-[55%_50%]',
            'tone' => 'brightness-115 contrast-110',
            'title' => 'Find the gym',
            'accent' => 'that fits you.',
            'text' => 'Open any gym to see its photos, timings, facilities and membership plans before you join.',
        ],
        3 => [
            'image' => 'images/register/step-3-owner.jpg',
            'position' => 'object-[35%_22%] lg:object-[30%_50%]',
            'tone' => 'brightness-105 contrast-110',
            'title' => 'Tell us about',
            'accent' => 'your goals.',
            'text' => 'Your gym uses these details to set up your membership, workouts and diet plan.',
        ],
        4 => [
            'image' => 'images/register/step-1-city.jpg',
            'position' => 'object-[50%_18%] lg:object-[45%_0%]',
            'tone' => 'brightness-90 contrast-115',
            'title' => 'One last',
            'accent' => 'rep.',
            'text' => 'Verify your mobile number — you\'ll use it to sign in to the Fitnix app.',
        ],
        5 => [
            'image' => 'images/register/step-1-city.jpg',
            'position' => 'object-[50%_18%] lg:object-[45%_0%]',
            'tone' => 'brightness-90 contrast-115',
            'title' => 'Welcome',
            'accent' => 'to the team.',
            'text' => 'Your gym has your registration. Once they approve it, track workouts, attendance and fees in the app.',
        ],
    ];
    foreach ($panels as $number => $panel) {
        $panels[$number]['eyebrow'] = isset($steps[$number]) ? 'Step '.$number.' · '.$steps[$number] : 'All done';
    }
    $chip = 'inline-flex items-center gap-1.5 rounded-full border border-gym-border bg-gym-charcoal px-3 py-1.5 text-gym-light';
    $optionCard = 'group flex items-center gap-4 rounded-xl border border-gym-border bg-gym-charcoal p-3 text-left transition duration-200 hover:border-gym-orange/60 hover:bg-gym-orange/5 hover:shadow-[0_0_28px_-10px_rgb(255_107_0/0.7)]';
    $initialsTile = 'flex size-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#2a2a2a] to-gym-black-deep text-lg font-bold text-gym-orange transition group-hover:from-gym-orange group-hover:to-gym-orange-hover group-hover:text-white';
@endphp
{{-- No backdrop blur on this card: it would trap the fixed gym pop-up inside the card. --}}
<div class="grid overflow-hidden rounded-[1.75rem] border border-gym-orange/40 bg-gym-black/95 shadow-[0_40px_90px_-40px_rgb(255_107_0/0.55)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
    {{-- Left: photo panel that follows the current step --}}
    <x-ui.photo-panel :panels="$panels" :current="$step" tagline="Find your gym. Train smarter."
                      :perks="[['map-pin', 'Gyms near you'], ['eye', 'Compare plans'], ['shield', 'Verified by OTP']]" />

    {{-- Right: the form --}}
    <div class="flex flex-col border-t border-gym-border p-6 sm:p-10 lg:border-t-0 lg:border-l lg:p-12">
        @if ($step < 5)
            <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Join a gym</h1>
            <p class="mt-3 text-gym-muted">Find your gym on Fitnix and register as a member. <strong class="text-gym-orange">The gym confirms your registration.</strong></p>

            {{-- Progress: City → Gym → Details → Verify --}}
            <ol class="mt-8 flex items-center gap-2.5" aria-label="Sign-up progress">
                @foreach ($steps as $n => $label)
                    @php $state = $n < $step ? 'done' : ($n === $step ? 'current' : 'upcoming'); @endphp
                    <li wire:key="step-{{ $n }}" class="flex flex-1 items-center gap-2.5 {{ $loop->last ? 'flex-none' : '' }}" @if ($state === 'current') aria-current="step" @endif>
                        <span @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition duration-300',
                            'border-2 border-gym-orange bg-gym-orange/15 text-gym-orange' => $state === 'done',
                            'bg-gym-orange text-white ring-4 ring-gym-orange/25 shadow-[0_0_24px_rgb(255_107_0/0.55)]' => $state === 'current',
                            'border-2 border-gym-border text-gym-muted' => $state === 'upcoming',
                        ])>
                            @if ($state === 'done')<x-ui.icon name="check" class="size-5" />@else{{ $n }}@endif
                        </span>
                        <span @class([
                            'hidden text-sm whitespace-nowrap xl:block',
                            'font-semibold text-white' => $state === 'current',
                            'font-medium text-gym-light' => $state === 'done',
                            'text-gym-muted' => $state === 'upcoming',
                        ])>{{ $label }}</span>
                        @unless ($loop->last)
                            <span @class([
                                'h-0.5 min-w-3 flex-1 rounded-full transition',
                                'bg-gym-orange' => $n < $step,
                                'bg-gradient-to-r from-gym-orange to-gym-border' => $n === $step,
                                'bg-gym-border' => $n > $step,
                            ])></span>
                        @endunless
                    </li>
                @endforeach
            </ol>
            <p class="mt-3 text-xs font-medium text-gym-muted xl:hidden">Step {{ $step }} of {{ count($steps) }} · {{ $steps[$step] }}</p>

            @if ($step > 1)
                <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                    @if ($city)<span class="{{ $chip }}"><x-ui.icon name="map-pin" class="size-4 text-gym-orange" />{{ $locality ? $locality->name.', ' : '' }}{{ $city->name }}</span>@endif
                    @if ($gym && $step > 2)<span class="{{ $chip }}"><x-ui.icon name="building" class="size-4 text-gym-orange" />{{ $gym->name }}</span>@endif
                    <button type="button" wire:click="back" class="ml-auto inline-flex items-center gap-1 font-medium text-gym-muted hover:text-gym-orange"><x-ui.icon name="arrow-left" class="size-4" /> Back</button>
                </div>
            @endif
        @endif

        {{-- Step 1b: locality in the chosen city --}}
        @if ($step === 1 && $choosingLocality)
            <div wire:key="step-locality" class="mt-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Which area of {{ $city?->name }} is your gym in?</h2>
                        <p class="mt-1 text-sm text-gym-muted">Pick an area, or see every gym in the city.</p>
                    </div>
                    <button type="button" wire:click="changeCity" class="shrink-0 text-sm font-medium text-gym-orange hover:underline">← Change city</button>
                </div>
                <div class="relative mt-5">
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" />
                    <input wire:model.live.debounce.300ms="localitySearch" type="search" class="input h-12 rounded-xl pl-12 text-base" placeholder="Type your area" aria-label="Search area" autofocus>
                </div>
                <div class="mt-4 grid max-h-80 gap-3 overflow-y-auto p-1 sm:grid-cols-2">
                    <button type="button" wire:click="selectLocality" class="{{ $optionCard }}">
                        <span class="{{ $initialsTile }}"><x-ui.icon name="globe" class="size-6" /></span>
                        <span class="min-w-0">
                            <span class="block truncate text-base font-semibold text-white">All areas</span>
                            <span class="block text-xs text-gym-muted">{{ $cityGymCount }} {{ \Illuminate\Support\Str::plural('gym', $cityGymCount) }}</span>
                        </span>
                    </button>
                    @foreach ($localities as $l)
                        <button type="button" wire:key="loc-{{ $l->id }}" wire:click="selectLocality({{ $l->id }})" class="{{ $optionCard }}">
                            <span class="{{ $initialsTile }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($l->name, 0, 2)) }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-base font-semibold text-white">{{ $l->name }}</span>
                                <span class="block text-xs text-gym-muted">{{ $l->gyms_count }} {{ \Illuminate\Support\Str::plural('gym', $l->gyms_count) }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
                @if (trim($localitySearch) !== '' && $localities->isEmpty())
                    <p class="mt-3 rounded-xl bg-gym-charcoal p-4 text-center text-sm text-gym-muted">No area matches "{{ trim($localitySearch) }}". Try "All areas" to see every gym in {{ $city?->name }}.</p>
                @endif
            </div>
        @endif

        {{-- Step 1: city --}}
        @if ($step === 1 && ! $choosingLocality)
            <div wire:key="step-city" class="mt-8">
                <h2 class="text-xl font-semibold text-white">Which city is your gym in?</h2>
                <p class="mt-1 text-sm text-gym-muted">Select your city to see the gyms there.</p>
                <div class="relative mt-5">
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" />
                    <input wire:model.live.debounce.300ms="citySearch" type="search" class="input h-12 rounded-xl pl-12 text-base" placeholder="Type your city" aria-label="Search city" autofocus>
                </div>
                <div class="mt-4 grid max-h-80 gap-3 overflow-y-auto p-1 sm:grid-cols-2">
                    @forelse ($cities as $c)
                        <button type="button" wire:key="city-{{ $c->id }}" wire:click="selectCity({{ $c->id }})" class="{{ $optionCard }}">
                            <span class="{{ $initialsTile }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($c->name, 0, 2)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-base font-semibold text-white">{{ $c->name }}</span>
                                @if ($c->state)<span class="block truncate text-xs text-gym-muted">{{ $c->state }}</span>@endif
                            </span>
                            <span class="shrink-0 rounded-full bg-gym-orange/10 px-2 py-0.5 text-xs font-medium text-gym-orange">{{ $c->gyms_count }} {{ \Illuminate\Support\Str::plural('gym', $c->gyms_count) }}</span>
                        </button>
                    @empty
                        <p class="col-span-full rounded-xl bg-gym-charcoal p-4 text-center text-sm text-gym-muted">
                            {{ trim($citySearch) !== '' ? 'No city matches "'.trim($citySearch).'".' : "Fitnix isn't live in any city yet. Please check back soon." }}
                        </p>
                    @endforelse
                </div>
                <p class="mt-4 flex items-center gap-2 text-sm text-gym-muted">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-5 shrink-0" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.25v3a.75.75 0 0 0 1.5 0v-3.75A.75.75 0 0 0 10 9H9Z" clip-rule="evenodd" /></svg>
                    Don't see your city? Fitnix isn't there yet.
                </p>
                <p class="mt-3 text-sm text-gym-muted">Just looking? <a href="{{ route('gyms.browse') }}" class="font-semibold text-gym-orange hover:underline">Browse gyms in your city</a></p>
            </div>
        @endif

        {{-- Step 2: gym in that city --}}
        @if ($step === 2)
            @php $where = ($locality ? $locality->name.', ' : '').$city?->name; @endphp
            <div wire:key="step-gym" class="mt-8">
                <h2 class="text-xl font-semibold text-white">Find your gym in {{ $where }}</h2>
                <p class="mt-1 text-sm text-gym-muted">Tap a gym to join it, or view its profile first.</p>
                <div class="relative mt-5">
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" />
                    <input wire:model.live.debounce.300ms="gymSearch" type="search" class="input h-12 rounded-xl pl-12 text-base" placeholder="Type your gym's name" aria-label="Search gym" autofocus>
                </div>
                <div class="mt-4 max-h-96 space-y-3 overflow-y-auto p-1">
                    @forelse ($gyms as $g)
                        <div wire:key="gym-{{ $g->id }}" class="group flex items-center gap-2 rounded-xl border border-gym-border bg-gym-charcoal pr-3 transition duration-200 hover:border-gym-orange/60 hover:bg-gym-orange/5 hover:shadow-[0_0_28px_-10px_rgb(255_107_0/0.7)]">
                            <button type="button" wire:click="selectGym({{ $g->id }})" class="flex min-w-0 flex-1 items-center gap-4 p-3 text-left">
                                @if ($g->logoUrl())
                                    <img src="{{ $g->logoUrl() }}" alt="" class="size-12 shrink-0 rounded-lg bg-white object-contain p-0.5">
                                @else
                                    <span class="{{ $initialsTile }}">{{ $g->initials() }}</span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate text-base font-semibold text-white">{{ $g->name }}</span>
                                    @php $area = $g->locality && ! \Illuminate\Support\Str::contains((string) $g->address, $g->locality->name, true) ? $g->locality->name : null; @endphp
                                    @if ($area || $g->address)<span class="block truncate text-xs text-gym-muted">{{ collect([$area, $g->address])->filter()->implode(' · ') }}</span>@endif
                                </span>
                            </button>
                            <button type="button" wire:click="viewGym({{ $g->id }})" class="btn-secondary btn-sm shrink-0 rounded-full"><x-ui.icon name="eye" class="size-4" /> View gym</button>
                        </div>
                    @empty
                        <p class="rounded-xl bg-gym-charcoal p-4 text-center text-sm text-gym-muted">
                            {{ trim($gymSearch) !== '' ? 'No gym in '.$where.' matches "'.trim($gymSearch).'".' : 'No gyms in '.$where.' yet.' }}
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Gym profile pop-up --}}
            @if ($preview)
                <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.closeGym()">
                    <div class="fixed inset-0 bg-gray-900/60" wire:click="closeGym"></div>
                    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                        <div class="card relative w-full overflow-hidden sm:max-w-2xl">
                            @if ($preview['banner_url'])
                                <img src="{{ $preview['banner_url'] }}" alt="" class="h-40 w-full object-cover">
                            @endif
                            <button type="button" wire:click="closeGym" class="absolute top-3 right-3 rounded-full bg-black/40 p-1.5 text-white hover:bg-black/60" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button>
    
                            <div class="card-body max-h-[70vh] space-y-5 overflow-y-auto">
                                <div class="flex items-center gap-3">
                                    @if ($preview['logo_url'])<img src="{{ $preview['logo_url'] }}" alt="" class="size-12 shrink-0 rounded-lg bg-white object-contain p-0.5">@endif
                                    <div class="min-w-0">
                                        <h3 class="truncate text-lg font-semibold">{{ $preview['name'] }}</h3>
                                        <p class="text-sm text-gray-500">{{ collect([$preview['locality'], $preview['city']])->filter()->implode(', ') }}</p>
                                    </div>
                                </div>
                                @if ($preview['tagline'])<p class="text-sm font-medium">{{ $preview['tagline'] }}</p>@endif
                                @if ($preview['description'])<p class="text-sm whitespace-pre-line text-gray-600">{{ \Illuminate\Support\Str::limit($preview['description'], 600) }}</p>@endif
    
                                @if ($preview['photos'])
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach ($preview['photos'] as $p)<img src="{{ $p['url'] }}" alt="{{ $p['caption'] }}" loading="lazy" class="aspect-[4/3] w-full rounded-lg object-cover">@endforeach
                                    </div>
                                @endif
    
                                <div class="grid gap-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <p class="font-semibold">Opening hours</p>
                                        <dl class="mt-2 space-y-1">
                                            @foreach ($preview['opening_hours'] as $h)
                                                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ $h['day'] }}</dt><dd>{{ $h['closed'] ? 'Closed' : $h['hours'] }}</dd></div>
                                            @endforeach
                                        </dl>
                                    </div>
                                    <div class="space-y-2">
                                        <p class="font-semibold">Location & contact</p>
                                        @if ($preview['address'])<p class="text-gray-600">{{ $preview['address'] }}</p>@endif
                                        <a href="{{ $preview['directions_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-brand-600 hover:underline"><x-ui.icon name="map-pin" class="size-4" /> {{ $preview['map_url'] ? 'Get directions' : 'Find on Google Maps' }}</a>
                                        @if ($preview['phone'])<p><a href="tel:{{ $preview['phone'] }}" class="text-gray-600 hover:underline">{{ $preview['phone'] }}</a></p>@endif
                                        @if ($preview['facilities'])<p class="text-gray-600"><span class="font-medium text-gray-700">Facilities:</span> {{ implode(', ', $preview['facilities']) }}</p>@endif
                                    </div>
                                </div>
    
                                @if ($preview['plans'])
                                    <div>
                                        <p class="text-sm font-semibold">Membership plans</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($preview['plans'] as $plan)
                                                <div class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-ink-700">
                                                    <div class="flex justify-between gap-2"><span class="font-medium">{{ $plan['name'] }}</span><span class="font-semibold">{{ $plan['price_label'] }}</span></div>
                                                    <p class="text-xs text-gray-500">{{ $plan['duration'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
    
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-5 py-4 dark:border-ink-700">
                                <a href="{{ $preview['public_url'] }}" target="_blank" rel="noopener" class="text-sm font-medium text-gray-500 hover:text-brand-600">Open full page ↗</a>
                                <button type="button" wire:click="selectGym({{ $preview['gym_id'] }})" class="btn-primary">Join this gym</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- Step 3: details --}}
        @if ($step === 3)
            <form wire:key="step-details" wire:submit="sendOtp" class="mt-8 flex flex-1 flex-col">
                <div>
                    <h2 class="text-xl font-semibold text-white">Your details</h2>
                    <p class="mt-1 text-sm text-gym-muted">Only your name and mobile number are required.</p>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Full name" name="name" required><input wire:model="name" class="input h-12 rounded-xl" autocomplete="name"></x-ui.field>
                    <x-ui.field label="Mobile number" name="phone" required hint="We'll send an OTP to verify it."><input wire:model="phone" type="tel" class="input h-12 rounded-xl" autocomplete="tel"></x-ui.field>
                    <x-ui.field label="Email" name="email"><input wire:model="email" type="email" class="input h-12 rounded-xl" autocomplete="email"></x-ui.field>
                    <x-ui.field label="Gender" name="gender">
                        <select wire:model="gender" class="input h-12 rounded-xl">
                            <option value="">Select…</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Date of birth" name="date_of_birth"><input wire:model="date_of_birth" type="date" class="input h-12 rounded-xl" max="{{ now()->subDay()->toDateString() }}"></x-ui.field>
                    <x-ui.field label="Fitness goal" name="fitness_goal">
                        <select wire:model="fitness_goal" class="input h-12 rounded-xl">
                            <option value="">Select…</option>
                            @foreach (['Weight loss', 'Muscle gain', 'General fitness', 'Strength training', 'Flexibility & mobility', 'Sports performance'] as $goal)<option>{{ $goal }}</option>@endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Address" name="address" class="sm:col-span-2"><input wire:model="address" class="input h-12 rounded-xl" autocomplete="street-address"></x-ui.field>
                    <x-ui.field label="Emergency contact" name="emergency_contact_name"><input wire:model="emergency_contact_name" class="input h-12 rounded-xl" placeholder="Name"></x-ui.field>
                    <x-ui.field label="Emergency phone" name="emergency_contact_phone"><input wire:model="emergency_contact_phone" type="tel" class="input h-12 rounded-xl"></x-ui.field>
                    <x-ui.field label="Medical conditions / injuries" name="medical_notes" class="sm:col-span-2"><textarea wire:model="medical_notes" rows="2" class="input rounded-xl" placeholder="Optional"></textarea></x-ui.field>
                </div>
                <div class="hidden" aria-hidden="true"><input wire:model="website" tabindex="-1" autocomplete="off"></div>
                <div class="mt-auto pt-8">
                    <button type="submit" class="btn-primary h-13 w-full rounded-full text-base" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="sendOtp" class="inline-flex items-center gap-2">Continue <x-ui.icon name="arrow-right" class="size-5" /></span>
                        <span wire:loading wire:target="sendOtp">Sending OTP…</span>
                    </button>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs text-gym-muted"><x-ui.icon name="shield" class="size-4 text-gym-orange" /> Your details are shared only with {{ $gym?->name }}.</p>
                </div>
            </form>
        @endif

        {{-- Step 4: verify phone --}}
        @if ($step === 4)
            <form wire:key="step-verify" wire:submit="verify" class="mt-8 flex flex-1 flex-col">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-gym-orange/15 text-gym-orange ring-1 ring-gym-orange/40">
                    <x-ui.icon name="shield" class="size-7" />
                </div>
                <h2 class="mt-5 text-xl font-semibold text-white">Verify your mobile number</h2>
                <p class="mt-1 text-sm text-gym-muted">Enter the {{ (int) config('gymai.otp.length', 6) }}-digit OTP sent to <strong class="text-white">{{ \App\Services\MemberOtpService::maskPhone($phone) }}</strong>.</p>
                <div class="mt-6">
                    <x-ui.field label="OTP" name="otp" required>
                        <input wire:model="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ (int) config('gymai.otp.length', 6) }}" class="input h-14 rounded-xl text-center text-2xl font-semibold tracking-[0.5em]" autofocus>
                    </x-ui.field>
                </div>
                <div class="mt-auto space-y-3 pt-8">
                    <button type="submit" class="btn-primary h-13 w-full rounded-full text-base" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="verify">Verify and register</span>
                        <span wire:loading wire:target="verify">Registering…</span>
                    </button>
                    <button type="button" wire:click="sendOtp" class="w-full text-sm font-medium text-gym-muted hover:text-gym-orange">Resend OTP</button>
                </div>
            </form>
        @endif

        {{-- Step 5: done --}}
        @if ($step === 5)
            <div class="my-auto py-6 text-center" @if ($registrationStatus === 'pending') wire:poll.10s @endif>
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-gym-orange text-white shadow-[0_0_36px_rgb(255_107_0/0.6)] ring-8 ring-gym-orange/20"><x-ui.icon name="check" class="size-8" /></div>
                <h1 class="mt-6 text-2xl font-bold tracking-tight text-white sm:text-3xl">Registration sent to {{ $gym?->name }}</h1>
                <div class="mx-auto mt-6 max-w-xs rounded-2xl border border-gym-orange/40 bg-gym-charcoal px-6 py-4">
                    <p class="text-xs font-semibold tracking-[0.2em] text-gym-muted uppercase">Your member ID</p>
                    <p class="mt-1 text-3xl font-bold tracking-wider text-gym-orange">{{ $memberCode }}</p>
                </div>

                <p class="mt-6 text-xs font-semibold tracking-wide text-gym-muted uppercase">Status</p>
                <x-ui.registration-status :status="$registrationStatus" class="mt-2" />

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    @if ($gym)<a href="{{ $gym->publicUrl() }}" class="btn-secondary h-12 rounded-full px-6">View {{ $gym->name }}</a>@endif
                    <a href="{{ route('login', ['mode' => 'otp']) }}" class="btn-primary h-12 rounded-full px-6">Sign in later to check</a>
                </div>
            </div>
        @endif

        @if ($step === 1)
            <div class="mt-8 flex items-center gap-4 text-sm text-gym-muted">
                <span class="h-px flex-1 bg-gym-border"></span>
                <span class="flex items-center gap-2">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                    Already a member? <a href="{{ route('login', ['mode' => 'otp']) }}" class="font-semibold text-gym-orange hover:underline">Sign in</a>
                </span>
                <span class="h-px flex-1 bg-gym-border"></span>
            </div>
        @endif
    </div>
</div>
