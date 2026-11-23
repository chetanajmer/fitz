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
                @if ($city)<span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-ink-800"><x-ui.icon name="globe" class="mr-1 inline size-4" />{{ $city->name }}</span>@endif
                @if ($gym && $step > 2)<span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-ink-800"><x-ui.icon name="building" class="mr-1 inline size-4" />{{ $gym->name }}</span>@endif
                <button type="button" wire:click="back" class="ml-auto text-sm font-medium text-gray-500 hover:text-brand-600">← Back</button>
            </div>
        @endif
    @endif

    {{-- Step 1: city --}}
    @if ($step === 1)
        <h2 class="mt-6 text-sm font-semibold">Which city is your gym in?</h2>
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
                <p class="text-sm text-gray-500 sm:col-span-2">Gymai isn't live in any city yet. Please check back soon.</p>
            @endforelse
        </div>
        <p class="mt-4 text-xs text-gray-500">Don't see your city? Gymai isn't there yet.</p>
    @endif

    {{-- Step 2: gym in that city --}}
    @if ($step === 2)
        <h2 class="mt-6 text-sm font-semibold">Find your gym in {{ $city?->name }}</h2>
        <input wire:model.live.debounce.300ms="gymSearch" type="search" class="input mt-3" placeholder="Type your gym's name" autofocus>
        <div class="mt-3 space-y-2">
            @forelse ($gyms as $g)
                <button type="button" wire:key="gym-{{ $g->id }}" wire:click="selectGym({{ $g->id }})"
                        class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left transition hover:border-brand-600 dark:border-ink-700">
                    @if ($g->logoUrl())
                        <img src="{{ $g->logoUrl() }}" alt="" class="size-10 shrink-0 rounded-lg bg-white object-contain p-0.5">
                    @else
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-600/15 text-sm font-bold text-brand-600">{{ $g->initials() }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $g->name }}</span>
                        @if ($g->address)<span class="block truncate text-xs text-gray-500">{{ $g->address }}</span>@endif
                    </span>
                </button>
            @empty
                <p class="py-4 text-center text-sm text-gray-500">
                    {{ trim($gymSearch) !== '' ? 'No gym in '.$city?->name.' matches "'.trim($gymSearch).'".' : 'No gyms in '.$city?->name.' yet.' }}
                </p>
            @endforelse
        </div>
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
        <div class="py-6 text-center">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><x-ui.icon name="check" class="size-7" /></div>
            <h1 class="mt-4 text-xl font-semibold">Registration sent to {{ $gym?->name }}</h1>
            <p class="mt-2 text-sm text-gray-500">Your member ID</p>
            <p class="text-2xl font-bold tracking-wider">{{ $memberCode }}</p>
            <p class="mx-auto mt-4 max-w-sm text-sm text-gray-500">The gym will confirm your registration. Once they do, log in to the Gymai app with your mobile number, and visit the front desk to choose a membership plan.</p>
            @if ($gym)<a href="{{ $gym->publicUrl() }}" class="btn-secondary mt-6">View {{ $gym->name }}</a>@endif
        </div>
    @endif
</div>
