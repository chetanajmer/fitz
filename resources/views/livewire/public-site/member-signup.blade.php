<div class="card card-body sm:p-8">
    @if ($step < 5)
        <h1 class="text-xl font-semibold">Join a gym</h1>
        <p class="mt-1 text-sm text-gray-500">Find your gym on Gymai and register as a member. The gym confirms your registration.</p>

        {{-- Progress: City → Gym → Details → Verify --}}
        <ol class="mt-6 grid grid-cols-4 gap-2 text-xs font-medium">
            @foreach ([1 => 'City', 2 => 'Gym', 3 => 'Your details', 4 => 'Verify phone'] as $n => $label)
                <li wire:key="step-{{ $n }}" class="flex flex-col gap-1.5">
                    <span class="h-1 rounded-full {{ $step >= $n ? 'bg-brand-600' : 'bg-gray-200 dark:bg-ink-700' }}"></span>
                    <span class="{{ $step === $n ? 'text-brand-600' : 'text-gray-500' }}">{{ $n }}. {{ $label }}</span>
                </li>
            @endforeach
        </ol>

        @if ($step > 1)
            <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                @if ($city)<span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-ink-800"><x-ui.icon name="globe" class="mr-1 inline size-4" />{{ $locality ? $locality->name.', ' : '' }}{{ $city->name }}</span>@endif
                @if ($gym && $step > 2)<span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-ink-800"><x-ui.icon name="building" class="mr-1 inline size-4" />{{ $gym->name }}</span>@endif
                <button type="button" wire:click="back" class="ml-auto text-sm font-medium text-gray-500 hover:text-brand-600">← Back</button>
            </div>
        @endif
    @endif

    {{-- Step 1b: locality in the chosen city --}}
    @if ($step === 1 && $choosingLocality)
        <div class="mt-6 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">Which area of {{ $city?->name }} is your gym in?</h2>
            <button type="button" wire:click="changeCity" class="text-sm font-medium text-gray-500 hover:text-brand-600">← Change city</button>
        </div>
        <input wire:model.live.debounce.300ms="localitySearch" type="search" class="input mt-3" placeholder="Type your area" autofocus>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <button type="button" wire:click="selectLocality" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-left transition hover:border-brand-600 dark:border-ink-700">
                <span class="font-medium">All areas</span>
                <span class="text-xs text-gray-500">{{ $cityGymCount }} {{ \Illuminate\Support\Str::plural('gym', $cityGymCount) }}</span>
            </button>
            @foreach ($localities as $l)
                <button type="button" wire:key="loc-{{ $l->id }}" wire:click="selectLocality({{ $l->id }})"
                        class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-left transition hover:border-brand-600 dark:border-ink-700">
                    <span class="font-medium">{{ $l->name }}</span>
                    <span class="text-xs text-gray-500">{{ $l->gyms_count }} {{ \Illuminate\Support\Str::plural('gym', $l->gyms_count) }}</span>
                </button>
            @endforeach
        </div>
        @if (trim($localitySearch) !== '' && $localities->isEmpty())
            <p class="mt-3 text-center text-sm text-gray-500">No area matches "{{ trim($localitySearch) }}". Try "All areas" to see every gym in {{ $city?->name }}.</p>
        @endif
    @endif

    {{-- Step 1: city --}}
    @if ($step === 1 && ! $choosingLocality)
        <h2 class="mt-6 text-sm font-semibold">Which city is your gym in?</h2>
        <input wire:model.live.debounce.300ms="citySearch" type="search" class="input mt-3" placeholder="Type your city" autofocus>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @forelse ($cities as $c)
                <button type="button" wire:key="city-{{ $c->id }}" wire:click="selectCity({{ $c->id }})"
                        class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-left transition hover:border-brand-600 dark:border-ink-700">
                    <span>
                        <span class="block font-medium">{{ $c->name }}</span>
                        @if ($c->state)<span class="block text-xs text-gray-500">{{ $c->state }}</span>@endif
                    </span>
                    <span class="text-xs text-gray-500">{{ $c->gyms_count }} {{ \Illuminate\Support\Str::plural('gym', $c->gyms_count) }}</span>
                </button>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 sm:col-span-2">
                    {{ trim($citySearch) !== '' ? 'No city matches "'.trim($citySearch).'".' : "Gymai isn't live in any city yet. Please check back soon." }}
                </p>
            @endforelse
        </div>
        <p class="mt-4 text-xs text-gray-500">Don't see your city? Gymai isn't there yet.</p>
        <p class="mt-2 text-sm text-gray-500">Just looking? <a href="{{ route('gyms.browse') }}" class="font-medium text-brand-600 hover:underline">Browse gyms in your city</a></p>
        <p class="mt-2 text-sm text-gray-500">Already a member? <a href="{{ route('login', ['mode' => 'otp']) }}" class="font-medium text-brand-600 hover:underline">Sign in</a></p>
    @endif

    {{-- Step 2: gym in that city --}}
    @if ($step === 2)
        @php $where = ($locality ? $locality->name.', ' : '').$city?->name; @endphp
        <h2 class="mt-6 text-sm font-semibold">Find your gym in {{ $where }}</h2>
        <input wire:model.live.debounce.300ms="gymSearch" type="search" class="input mt-3" placeholder="Type your gym's name" autofocus>
        <div class="mt-3 space-y-2">
            @forelse ($gyms as $g)
                <div wire:key="gym-{{ $g->id }}" class="flex items-center gap-2 rounded-lg border border-gray-200 pr-3 transition hover:border-brand-600 dark:border-ink-700">
                    <button type="button" wire:click="selectGym({{ $g->id }})" class="flex min-w-0 flex-1 items-center gap-3 px-4 py-3 text-left">
                        @if ($g->logoUrl())
                            <img src="{{ $g->logoUrl() }}" alt="" class="size-10 shrink-0 rounded-lg bg-white object-contain p-0.5">
                        @else
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-600/15 text-sm font-bold text-brand-600">{{ $g->initials() }}</span>
                        @endif
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $g->name }}</span>
                            @php $area = $g->locality && ! \Illuminate\Support\Str::contains((string) $g->address, $g->locality->name, true) ? $g->locality->name : null; @endphp
                            @if ($area || $g->address)<span class="block truncate text-xs text-gray-500">{{ collect([$area, $g->address])->filter()->implode(' · ') }}</span>@endif
                        </span>
                    </button>
                    <button type="button" wire:click="viewGym({{ $g->id }})" class="btn-secondary btn-sm shrink-0"><x-ui.icon name="eye" class="size-4" /> View gym</button>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500">
                    {{ trim($gymSearch) !== '' ? 'No gym in '.$where.' matches "'.trim($gymSearch).'".' : 'No gyms in '.$where.' yet.' }}
                </p>
            @endforelse
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
        <form wire:submit="sendOtp" class="mt-6 space-y-4">
            <h2 class="text-sm font-semibold">Your details</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Full name" name="name" required><input wire:model="name" class="input" autocomplete="name"></x-ui.field>
                <x-ui.field label="Mobile number" name="phone" required hint="We'll send an OTP to verify it."><input wire:model="phone" type="tel" class="input" autocomplete="tel"></x-ui.field>
                <x-ui.field label="Email" name="email"><input wire:model="email" type="email" class="input" autocomplete="email"></x-ui.field>
                <x-ui.field label="Gender" name="gender">
                    <select wire:model="gender" class="input">
                        <option value="">Select…</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Date of birth" name="date_of_birth"><input wire:model="date_of_birth" type="date" class="input" max="{{ now()->subDay()->toDateString() }}"></x-ui.field>
                <x-ui.field label="Fitness goal" name="fitness_goal">
                    <select wire:model="fitness_goal" class="input">
                        <option value="">Select…</option>
                        @foreach (['Weight loss', 'Muscle gain', 'General fitness', 'Strength training', 'Flexibility & mobility', 'Sports performance'] as $goal)<option>{{ $goal }}</option>@endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Address" name="address" class="sm:col-span-2"><input wire:model="address" class="input" autocomplete="street-address"></x-ui.field>
                <x-ui.field label="Emergency contact" name="emergency_contact_name"><input wire:model="emergency_contact_name" class="input" placeholder="Name"></x-ui.field>
                <x-ui.field label="Emergency phone" name="emergency_contact_phone"><input wire:model="emergency_contact_phone" type="tel" class="input"></x-ui.field>
                <x-ui.field label="Medical conditions / injuries" name="medical_notes" class="sm:col-span-2"><textarea wire:model="medical_notes" rows="2" class="input" placeholder="Optional"></textarea></x-ui.field>
            </div>
            <div class="hidden" aria-hidden="true"><input wire:model="website" tabindex="-1" autocomplete="off"></div>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sendOtp">Continue</span>
                <span wire:loading wire:target="sendOtp">Sending OTP…</span>
            </button>
            <p class="text-center text-xs text-gray-500">Your details are shared only with {{ $gym?->name }}.</p>
        </form>
    @endif

    {{-- Step 4: verify phone --}}
    @if ($step === 4)
        <form wire:submit="verify" class="mt-6 space-y-4">
            <h2 class="text-sm font-semibold">Verify your mobile number</h2>
            <p class="text-sm text-gray-500">Enter the {{ (int) config('gymai.otp.length', 6) }}-digit OTP sent to {{ \App\Services\MemberOtpService::maskPhone($phone) }}.</p>
            <x-ui.field label="OTP" name="otp" required>
                <input wire:model="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ (int) config('gymai.otp.length', 6) }}" class="input text-center text-lg tracking-[0.5em]">
            </x-ui.field>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="verify">Verify and register</span>
                <span wire:loading wire:target="verify">Registering…</span>
            </button>
            <button type="button" wire:click="sendOtp" class="w-full text-sm text-gray-500 hover:text-brand-600">Resend OTP</button>
        </form>
    @endif

    {{-- Step 5: done --}}
    @if ($step === 5)
        <div class="py-6 text-center" @if ($registrationStatus === 'pending') wire:poll.10s @endif>
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-ui.icon name="check" class="size-7" /></div>
            <h1 class="mt-4 text-xl font-semibold">Registration sent to {{ $gym?->name }}</h1>
            <p class="mt-2 text-sm text-gray-500">Your member ID</p>
            <p class="text-2xl font-bold tracking-wider">{{ $memberCode }}</p>

            <p class="mt-6 text-xs font-semibold tracking-wide text-gray-500 uppercase">Status</p>
            <x-ui.registration-status :status="$registrationStatus" class="mt-2" />

            <div class="mt-6 flex flex-wrap justify-center gap-2">
                @if ($gym)<a href="{{ $gym->publicUrl() }}" class="btn-secondary">View {{ $gym->name }}</a>@endif
                <a href="{{ route('login', ['mode' => 'otp']) }}" class="btn-secondary">Sign in later to check</a>
            </div>
        </div>
    @endif
</div>
