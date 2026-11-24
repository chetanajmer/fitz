@php
    $selectedCity = $cities->firstWhere('id', (int) $city_id);
    $lastStep = count($steps);
@endphp
<div class="card card-body sm:p-8">
    <h1 class="text-xl font-semibold">Register your gym</h1>
    <p class="mt-1 text-sm text-gray-500">
        No setup fee. No registration fee. All features included — just <strong>{{ money($price, false) }} per active member / month</strong>.
    </p>

    @unless ($open)
        <div class="mt-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">New gym registrations are temporarily closed. Please check back soon.</div>
    @else
        {{-- Step indicator --}}
        <ol class="mt-6 flex items-center gap-2" aria-label="Registration progress">
            @foreach ($steps as $number => $label)
                @php($state = $number < $step ? 'done' : ($number === $step ? 'current' : 'upcoming'))
                <li class="flex flex-1 items-center gap-2 {{ $loop->last ? 'flex-none' : '' }}">
                    <button type="button" wire:click="goToStep({{ $number }})" @disabled($number >= $step)
                            class="flex items-center gap-2 text-left {{ $number < $step ? 'cursor-pointer' : 'cursor-default' }}"
                            @if ($state === 'current') aria-current="step" @endif>
                        <span @class([
                            'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition',
                            'bg-brand-600 text-white' => $state === 'done',
                            'bg-brand-600 text-white ring-4 ring-brand-600/25' => $state === 'current',
                            'border border-gray-300 text-gray-400' => $state === 'upcoming',
                        ])>
                            @if ($state === 'done')<x-ui.icon name="check" class="size-4" />@else{{ $number }}@endif
                        </span>
                        <span class="hidden text-sm font-medium sm:block {{ $state === 'upcoming' ? 'text-gray-400' : 'text-gray-900' }}">{{ $label }}</span>
                    </button>
                    @unless ($loop->last)
                        <span class="h-0.5 flex-1 rounded-full {{ $number < $step ? 'bg-brand-600' : 'bg-gray-200' }}"></span>
                    @endunless
                </li>
            @endforeach
        </ol>
        <p class="mt-3 text-xs font-medium text-gray-500 sm:hidden">Step {{ $step }} of {{ $lastStep }} · {{ $steps[$step] }}</p>

        <form wire:submit="{{ $step < $lastStep ? 'next' : 'register' }}" class="mt-6">
            @if ($step === 1)
                <div wire:key="step-city" x-data="{ q: '' }">
                    <h2 class="font-semibold">Where is your gym?</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Select the city your gym is in.</p>

                    <div class="relative mt-4">
                        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
                        <input x-model="q" type="search" class="input pl-9" placeholder="Search city…" aria-label="Search city">
                    </div>

                    <div class="mt-3 grid max-h-72 grid-cols-2 gap-2 overflow-y-auto pr-1 sm:grid-cols-3">
                        @forelse ($cities as $c)
                            @php($isSelected = (int) $city_id === $c->id)
                            <button type="button" wire:key="city-{{ $c->id }}" wire:click="selectCity({{ $c->id }})"
                                    x-show="! q || @js(\Illuminate\Support\Str::lower($c->name.' '.$c->state)).includes(q.toLowerCase())"
                                    @class([
                                        'flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition',
                                        'border-brand-600 bg-brand-600/10 ring-1 ring-brand-600' => $isSelected,
                                        'border-gray-200 hover:border-brand-600/50 hover:bg-gray-50' => ! $isSelected,
                                    ])
                                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $isSelected ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-500' }}">
                                    <x-ui.icon name="map-pin" class="size-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $c->name }}</span>
                                    @if ($c->state)<span class="block truncate text-xs text-gray-500">{{ $c->state }}</span>@endif
                                </span>
                            </button>
                        @empty
                            <p class="col-span-full rounded-lg bg-gray-50 p-4 text-sm text-gray-500">No cities are available yet. Please contact Gymai support.</p>
                        @endforelse
                    </div>
                    @error('city_id') <p class="input-error">{{ $message }}</p> @enderror
                    <p class="hint mt-3">Your city isn't listed? Contact Gymai support and we'll add it.</p>
                </div>
            @elseif ($step === 2)
                <div wire:key="step-gym" class="space-y-4">
                    <div>
                        <h2 class="font-semibold">Tell us about your gym</h2>
                        <p class="mt-0.5 flex items-center gap-1 text-sm text-gray-500">
                            <x-ui.icon name="map-pin" class="size-4 text-brand-500" /> {{ $selectedCity?->name }}{{ $selectedCity?->state ? ', '.$selectedCity->state : '' }}
                            <button type="button" wire:click="goToStep(1)" class="ml-1 font-medium text-brand-600 hover:underline">Change</button>
                        </p>
                    </div>
                    <x-ui.field label="Gym name" name="gym_name" required>
                        <input wire:model="gym_name" id="gym_name" class="input" placeholder="Iron Paradise Fitness" autofocus>
                    </x-ui.field>
                    <x-ui.field label="Gym location link" name="map_url">
                        <div class="relative">
                            <x-ui.icon name="link" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
                            <input wire:model="map_url" id="map_url" type="url" inputmode="url" class="input pl-9" placeholder="https://maps.app.goo.gl/…">
                        </div>
                        <p class="hint">Open <a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" class="font-medium text-brand-600 hover:underline">Google Maps</a>, find your gym, tap <strong>Share</strong> and paste the link here. Members use it for directions.</p>
                    </x-ui.field>
                    <x-ui.field label="Locality / area" name="locality" hint="Members find gyms by area. Pick yours from the list or type it.">
                        <input wire:model="locality" id="locality" list="register-localities" class="input" placeholder="e.g. Jagatpura" autocomplete="off">
                        <datalist id="register-localities">@foreach ($localities as $l)<option value="{{ $l }}">@endforeach</datalist>
                    </x-ui.field>
                    <x-ui.field label="Address" name="address">
                        <input wire:model="address" id="address" class="input" placeholder="Shop no., street, area">
                    </x-ui.field>
                </div>
            @else
                <div wire:key="step-owner" class="space-y-4">
                    <div>
                        <h2 class="font-semibold">Create your owner account</h2>
                        <p class="mt-0.5 text-sm text-gray-500">You'll use these details to sign in to <strong>{{ $gym_name }}</strong>'s dashboard.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Your name" name="owner_name" required>
                            <input wire:model="owner_name" id="owner_name" class="input" autocomplete="name" autofocus>
                        </x-ui.field>
                        <x-ui.field label="Mobile number" name="phone" required>
                            <input wire:model="phone" id="phone" class="input" autocomplete="tel">
                        </x-ui.field>
                        <x-ui.field label="Email" name="email" required class="sm:col-span-2">
                            <input wire:model="email" id="email" type="email" class="input" autocomplete="email">
                        </x-ui.field>
                        <x-ui.field label="Password" name="password" required>
                            <input wire:model="password" id="password" type="password" class="input" autocomplete="new-password">
                        </x-ui.field>
                        <x-ui.field label="Confirm password" name="password_confirmation" required>
                            <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input" autocomplete="new-password">
                        </x-ui.field>
                    </div>
                    <x-ui.field name="terms">
                        <label class="flex items-start gap-2 text-sm text-gray-600">
                            <input wire:model="terms" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                            I agree to be billed {{ money($price, false) }} per active member per month. Enquiries, expired and inactive members are never billed.
                        </label>
                    </x-ui.field>
                </div>
            @endif

            <div class="mt-6 flex items-center gap-3">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="btn-secondary">
                        <x-ui.icon name="arrow-left" class="size-4" /> Back
                    </button>
                @endif
                @if ($step < $lastStep)
                    <button type="submit" class="btn-primary flex-1" wire:loading.attr="disabled" wire:target="next">
                        Continue <x-ui.icon name="arrow-right" class="size-4" />
                    </button>
                @else
                    <button type="submit" class="btn-primary flex-1" wire:loading.attr="disabled" wire:target="register">
                        <span wire:loading.remove wire:target="register">Create my gym</span>
                        <span wire:loading wire:target="register">Setting up…</span>
                    </button>
                @endif
            </div>
        </form>
    @endunless

    <p class="mt-6 text-center text-sm text-gray-500">
        Already registered? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Sign in</a>
    </p>
</div>
