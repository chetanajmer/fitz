@php
    $selectedCity = $cities->firstWhere('id', (int) $city_id);
    $lastStep = count($steps);
    // Left panel: one photo and message per step; they cross-fade as the owner moves through the form.
    $panels = [
        1 => [
            'image' => 'images/register/step-1-city.jpg',
            'position' => 'object-[50%_18%] lg:object-[45%_0%]',
            'tone' => 'brightness-90 contrast-115',
            'title' => 'Every big gym starts',
            'accent' => 'with one step.',
            'text' => 'Pick your city. Members nearby will find your gym, compare your plans and join online.',
        ],
        2 => [
            'image' => 'images/register/step-2-gym.jpg',
            'position' => 'object-[55%_30%] lg:object-[55%_50%]',
            'tone' => 'brightness-115 contrast-110',
            'title' => 'Build your gym\'s',
            'accent' => 'brand.',
            'text' => 'Add your gym name, location and area — your public gym page goes live the moment you finish.',
        ],
        3 => [
            'image' => 'images/register/step-3-owner.jpg',
            'position' => 'object-[35%_22%] lg:object-[30%_50%]',
            'tone' => 'brightness-105 contrast-110',
            'title' => 'Take charge of',
            'accent' => 'your gym.',
            'text' => 'Create your owner account and run members, fees, attendance, trainers and the AI Coach from one dashboard.',
        ],
    ];
    foreach ($panels as $number => $panel) {
        $panels[$number]['eyebrow'] = 'Step '.$number.' · '.$steps[$number];
    }
@endphp
<div class="grid overflow-hidden rounded-[1.75rem] border border-gym-orange/40 bg-gym-black/85 shadow-[0_40px_90px_-40px_rgb(255_107_0/0.55)] backdrop-blur-xl lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
    {{-- Left: photo panel that follows the current step --}}
    <x-ui.photo-panel :panels="$panels" :current="$step" :perks="[['bolt', 'Get started instantly'], ['shield', 'Secure & trusted'], ['chart', 'Grow your business']]" />

    {{-- Right: the form --}}
    <div class="flex flex-col border-t border-gym-border p-6 sm:p-10 lg:border-t-0 lg:border-l lg:p-12">
        <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Register your gym</h1>
        <p class="mt-3 text-gym-muted">
            No setup fee. No registration fee. All features included — just <strong class="text-white">{{ money($price, false) }}</strong>
            <strong class="text-gym-orange">per active member / month</strong>.
        </p>

        @unless ($open)
            <div class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">New gym registrations are temporarily closed. Please check back soon.</div>
        @else
            {{-- Step indicator --}}
            <ol class="mt-8 flex items-center gap-3" aria-label="Registration progress">
                @foreach ($steps as $number => $label)
                    @php($state = $number < $step ? 'done' : ($number === $step ? 'current' : 'upcoming'))
                    <li class="flex flex-1 items-center gap-3 {{ $loop->last ? 'flex-none' : '' }}">
                        <button type="button" wire:click="goToStep({{ $number }})" @disabled($number >= $step)
                                class="flex items-center gap-3 text-left {{ $number < $step ? 'cursor-pointer' : 'cursor-default' }}"
                                @if ($state === 'current') aria-current="step" @endif>
                            <span @class([
                                'flex size-11 shrink-0 items-center justify-center rounded-full text-base font-semibold transition duration-300',
                                'border-2 border-gym-orange bg-gym-orange/15 text-gym-orange' => $state === 'done',
                                'bg-gym-orange text-white ring-4 ring-gym-orange/25 shadow-[0_0_24px_rgb(255_107_0/0.55)]' => $state === 'current',
                                'border-2 border-gym-border text-gym-muted' => $state === 'upcoming',
                            ])>
                                @if ($state === 'done')<x-ui.icon name="check" class="size-5" />@else{{ $number }}@endif
                            </span>
                            <span @class([
                                'hidden text-sm sm:block',
                                'font-semibold text-white' => $state === 'current',
                                'font-medium text-gym-light' => $state === 'done',
                                'text-gym-muted' => $state === 'upcoming',
                            ])>{{ $label }}</span>
                        </button>
                        @unless ($loop->last)
                            <span @class([
                                'h-0.5 min-w-4 flex-1 rounded-full transition',
                                'bg-gym-orange' => $number < $step,
                                'bg-gradient-to-r from-gym-orange to-gym-border' => $number === $step,
                                'bg-gym-border' => $number > $step,
                            ])></span>
                        @endunless
                    </li>
                @endforeach
            </ol>
            <p class="mt-3 text-xs font-medium text-gym-muted sm:hidden">Step {{ $step }} of {{ $lastStep }} · {{ $steps[$step] }}</p>

            <form wire:submit="{{ $step < $lastStep ? 'next' : 'register' }}" class="mt-8 flex flex-1 flex-col">
                @if ($step === 1)
                    <div wire:key="step-city" x-data="{ q: '' }">
                        <h2 class="text-xl font-semibold text-white">Where is your gym?</h2>
                        <p class="mt-1 text-sm text-gym-muted">Select the city your gym is in.</p>

                        <div class="relative mt-5">
                            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" />
                            <input x-model="q" type="search" class="input h-12 rounded-xl pl-12 text-base" placeholder="Search city…" aria-label="Search city">
                        </div>

                        <div class="mt-4 grid max-h-72 gap-3 overflow-y-auto p-1 sm:grid-cols-2">
                            @forelse ($cities as $c)
                                @php($isSelected = (int) $city_id === $c->id)
                                <button type="button" wire:key="city-{{ $c->id }}" wire:click="selectCity({{ $c->id }})"
                                        x-show="! q || @js(\Illuminate\Support\Str::lower($c->name.' '.$c->state)).includes(q.toLowerCase())"
                                        @class([
                                            'relative flex items-center gap-4 rounded-xl border p-3 text-left transition duration-200',
                                            'border-gym-orange bg-gym-orange/10 shadow-[0_0_28px_-8px_rgb(255_107_0/0.7)]' => $isSelected,
                                            'border-gym-border bg-gym-charcoal hover:border-gym-orange/50 hover:bg-gym-orange/5' => ! $isSelected,
                                        ])
                                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}">
                                    <span @class([
                                        'flex size-14 shrink-0 items-center justify-center rounded-lg text-xl font-bold',
                                        'bg-gradient-to-br from-gym-orange to-gym-orange-hover text-white' => $isSelected,
                                        'bg-gradient-to-br from-[#2a2a2a] to-gym-black-deep text-gym-orange' => ! $isSelected,
                                    ])>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($c->name, 0, 2)) }}</span>
                                    <span class="flex min-w-0 items-center gap-2">
                                        <x-ui.icon name="map-pin" class="size-5 shrink-0 text-gym-orange" />
                                        <span class="min-w-0">
                                            <span class="block truncate text-base font-semibold text-white">{{ $c->name }}</span>
                                            @if ($c->state)<span class="block truncate text-xs text-gym-muted">{{ $c->state }}</span>@endif
                                        </span>
                                    </span>
                                    @if ($isSelected)
                                        <span class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-gym-orange text-white"><x-ui.icon name="check" class="size-3.5" /></span>
                                    @endif
                                </button>
                            @empty
                                <p class="col-span-full rounded-xl bg-gym-charcoal p-4 text-sm text-gym-muted">No cities are available yet. Please contact Fitnix support.</p>
                            @endforelse
                        </div>
                        @error('city_id') <p class="input-error">{{ $message }}</p> @enderror
                        <p class="mt-4 flex items-center gap-2 text-sm text-gym-muted">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5 shrink-0" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.25v3a.75.75 0 0 0 1.5 0v-3.75A.75.75 0 0 0 10 9H9Z" clip-rule="evenodd" /></svg>
                            Your city isn't listed? Contact Fitnix support and we'll add it.
                        </p>
                    </div>
                @elseif ($step === 2)
                    <div wire:key="step-gym" class="space-y-4">
                        <div>
                            <h2 class="text-xl font-semibold text-white">Tell us about your gym</h2>
                            <p class="mt-1 flex items-center gap-1 text-sm text-gym-muted">
                                <x-ui.icon name="map-pin" class="size-4 text-gym-orange" /> {{ $selectedCity?->name }}{{ $selectedCity?->state ? ', '.$selectedCity->state : '' }}
                                <button type="button" wire:click="goToStep(1)" class="ml-1 font-medium text-gym-orange hover:underline">Change</button>
                            </p>
                        </div>
                        <x-ui.field label="Gym name" name="gym_name" required>
                            <input wire:model="gym_name" id="gym_name" class="input h-12 rounded-xl" placeholder="Iron Paradise Fitness" autofocus>
                        </x-ui.field>
                        <x-ui.field label="Gym location link" name="map_url">
                            <div class="relative">
                                <x-ui.icon name="link" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-gym-muted" />
                                <input wire:model="map_url" id="map_url" type="url" inputmode="url" class="input h-12 rounded-xl pl-11" placeholder="https://maps.app.goo.gl/…">
                            </div>
                            <p class="hint">Open <a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer" class="font-medium text-gym-orange hover:underline">Google Maps</a>, find your gym, tap <strong>Share</strong> and paste the link here. Members use it for directions.</p>
                        </x-ui.field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Locality / area" name="locality" hint="Pick from the list or type it.">
                                <input wire:model="locality" id="locality" list="register-localities" class="input h-12 rounded-xl" placeholder="e.g. Jagatpura" autocomplete="off">
                                <datalist id="register-localities">@foreach ($localities as $l)<option value="{{ $l }}">@endforeach</datalist>
                            </x-ui.field>
                            <x-ui.field label="Address" name="address">
                                <input wire:model="address" id="address" class="input h-12 rounded-xl" placeholder="Shop no., street, area">
                            </x-ui.field>
                        </div>
                    </div>
                @else
                    <div wire:key="step-owner" class="space-y-4">
                        <div>
                            <h2 class="text-xl font-semibold text-white">Create your owner account</h2>
                            <p class="mt-1 text-sm text-gym-muted">You'll use these details to sign in to <strong class="text-white">{{ $gym_name }}</strong>'s dashboard.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Your name" name="owner_name" required>
                                <input wire:model="owner_name" id="owner_name" class="input h-12 rounded-xl" autocomplete="name" autofocus>
                            </x-ui.field>
                            <x-ui.field label="Mobile number" name="phone" required>
                                <input wire:model="phone" id="phone" class="input h-12 rounded-xl" autocomplete="tel">
                            </x-ui.field>
                            <x-ui.field label="Email" name="email" required class="sm:col-span-2">
                                <input wire:model="email" id="email" type="email" class="input h-12 rounded-xl" autocomplete="email">
                            </x-ui.field>
                            <x-ui.field label="Password" name="password" required>
                                <input wire:model="password" id="password" type="password" class="input h-12 rounded-xl" autocomplete="new-password">
                            </x-ui.field>
                            <x-ui.field label="Confirm password" name="password_confirmation" required>
                                <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input h-12 rounded-xl" autocomplete="new-password">
                            </x-ui.field>
                        </div>
                        <x-ui.field name="terms">
                            <label class="flex items-start gap-2.5 text-sm text-gym-muted">
                                <input wire:model="terms" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                                I agree to be billed {{ money($price, false) }} per active member per month. Enquiries, expired and inactive members are never billed.
                            </label>
                        </x-ui.field>
                    </div>
                @endif

                <div class="mt-auto flex items-center gap-3 pt-8">
                    @if ($step > 1)
                        <button type="button" wire:click="back" class="btn-secondary h-13 rounded-full px-6 text-base">
                            <x-ui.icon name="arrow-left" class="size-4" /> Back
                        </button>
                    @endif
                    @if ($step < $lastStep)
                        <button type="submit" class="btn-primary h-13 flex-1 rounded-full text-base" wire:loading.attr="disabled" wire:target="next">
                            Continue <x-ui.icon name="arrow-right" class="size-5" />
                        </button>
                    @else
                        <button type="submit" class="btn-primary h-13 flex-1 rounded-full text-base" wire:loading.attr="disabled" wire:target="register">
                            <span wire:loading.remove wire:target="register">Create my gym</span>
                            <span wire:loading wire:target="register">Setting up…</span>
                        </button>
                    @endif
                </div>
            </form>
        @endunless

        <div class="mt-8 flex items-center gap-4 text-sm text-gym-muted">
            <span class="h-px flex-1 bg-gym-border"></span>
            <span class="flex items-center gap-2">
                <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                Already registered? <a href="{{ route('login') }}" class="font-semibold text-gym-orange hover:underline">Sign in</a>
            </span>
            <span class="h-px flex-1 bg-gym-border"></span>
        </div>
    </div>
</div>
