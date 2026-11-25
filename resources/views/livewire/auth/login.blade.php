@php
    // Left panel: one photo; the message follows the sign-in method (and the registration status view).
    $photo = [
        'image' => 'images/login/login-athlete.jpg',
        'position' => 'object-[50%_12%] lg:object-[50%_0%]',
        'tone' => 'brightness-75 contrast-125',
    ];
    $panels = [
        'password' => $photo + [
            'eyebrow' => 'Welcome back',
            'title' => 'No excuses.',
            'accent' => 'Just results.',
            'text' => 'Sign in to run your gym, track your workouts and keep every rep on record.',
        ],
        'otp' => $photo + [
            'eyebrow' => 'Sign in with OTP',
            'title' => 'No password?',
            'accent' => 'No problem.',
            'text' => 'Get a one-time code on your mobile or email and you\'re in within seconds.',
        ],
        'status' => $photo + [
            'eyebrow' => 'Your registration',
            'title' => 'Stay strong.',
            'accent' => 'We\'re on it.',
            'text' => 'Here is where your gym registration stands right now.',
        ],
    ];
    $current = $statuses ? 'status' : $mode;
    $tab = 'flex items-center justify-center gap-2 rounded-full px-3 py-2.5 transition';
    $checkbox = 'size-4 rounded border-gym-border bg-gym-charcoal text-gym-orange focus:ring-gym-orange/40';
@endphp
<div class="grid overflow-hidden rounded-[1.75rem] border border-gym-orange/40 bg-gym-black/85 shadow-[0_40px_90px_-40px_rgb(255_107_0/0.55)] backdrop-blur-xl lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
    {{-- Left: photo panel --}}
    <x-ui.photo-panel :panels="$panels" :current="$current"
                      :perks="[['bolt', 'Fast sign-in'], ['shield', 'Secure & private'], ['chart', 'Track progress']]" />

    {{-- Right: the form --}}
    <div class="flex flex-col justify-center border-t border-gym-border p-6 sm:p-10 lg:border-t-0 lg:border-l lg:p-12">
        <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Welcome back</h1>
        <p class="mt-3 text-gym-muted">Sign in to your <strong class="text-gym-orange">Gymai</strong> account.</p>

        <div class="mt-8 grid grid-cols-2 gap-1 rounded-full border border-gym-border bg-gym-charcoal p-1 text-sm font-semibold">
            <button type="button" wire:click="$set('mode', 'password')" @class([$tab, 'bg-gym-orange text-white shadow-[0_0_24px_-4px_rgb(255_107_0/0.7)]' => $mode === 'password', 'text-gym-muted hover:text-white' => $mode !== 'password'])>
                <svg viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                Password
            </button>
            <button type="button" wire:click="$set('mode', 'otp')" @class([$tab, 'bg-gym-orange text-white shadow-[0_0_24px_-4px_rgb(255_107_0/0.7)]' => $mode === 'otp', 'text-gym-muted hover:text-white' => $mode !== 'otp'])>
                <x-ui.icon name="shield" class="size-4" /> OTP
            </button>
        </div>

        @if ($statuses)
            {{-- A verified number that cannot sign in yet: where each registration stands. --}}
            <div class="mt-8 space-y-3">
                @foreach ($statuses as $i => $s)
                    <div wire:key="status-{{ $i }}" class="rounded-xl border border-gym-border bg-gym-charcoal p-4 text-sm text-gym-light">
                        <p class="text-base font-semibold text-white">{{ $s['gym'] }}</p>
                        <p class="text-xs text-gym-muted">Registered {{ $s['registered_on'] }}@if ($s['member_code']) · Member ID {{ $s['member_code'] }}@endif</p>
                        @if ($s['status'] === 'pending')
                            <p class="mt-3 flex items-start gap-2"><x-ui.icon name="clock" class="mt-0.5 size-5 shrink-0 text-gym-orange" />
                                <span><strong class="text-white">Waiting for approval.</strong> {{ $s['gym'] }} hasn't confirmed your registration yet. You can sign in here as soon as they do.</span></p>
                        @elseif ($s['status'] === 'declined')
                            <p class="mt-3 text-2xl" aria-hidden="true">😔</p>
                            <p class="mt-1"><strong class="text-white">Sorry, {{ $s['gym'] }} couldn't approve your registration.</strong> This can happen if the gym is full or couldn't confirm your details. Please contact the gym, or find another gym near you.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('gyms.browse') }}" class="btn-secondary btn-sm rounded-full">Browse gyms</a>
                                <a href="{{ route('member.join') }}" class="btn-primary btn-sm rounded-full">Join another gym</a>
                            </div>
                        @else
                            <p class="mt-3 flex items-start gap-2"><x-ui.icon name="warning" class="mt-0.5 size-5 shrink-0 text-gym-muted" />
                                <span><strong class="text-white">Your membership is inactive.</strong> Please contact {{ $s['gym'] }} to reactivate it.</span></p>
                        @endif
                    </div>
                @endforeach
                <button type="button" wire:click="changeIdentifier" class="w-full text-center text-sm font-medium text-gym-muted hover:text-gym-orange">Use another number or email</button>
            </div>
        @elseif ($mode === 'password')
            <form wire:key="form-password" wire:submit="login" class="mt-8 space-y-5">
                <x-ui.field label="Email or mobile number" name="email" hint="Gym staff can use their verified mobile number.">
                    <div class="relative">
                        <x-ui.icon name="user" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" />
                        <input wire:model="email" id="email" type="text" autocomplete="username" autofocus class="input h-12 rounded-xl pl-12">
                    </div>
                </x-ui.field>
                <x-ui.field label="Password" name="password">
                    <div class="relative">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" /></svg>
                        <input wire:model="password" id="password" type="password" autocomplete="current-password" class="input h-12 rounded-xl pl-12">
                    </div>
                </x-ui.field>
                <label class="flex items-center gap-2.5 text-sm text-gym-muted">
                    <input wire:model="remember" type="checkbox" class="{{ $checkbox }}"> Remember me
                </label>
                <button type="submit" class="btn-primary h-13 w-full rounded-full text-base" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="login" class="inline-flex items-center gap-2">Sign in <x-ui.icon name="arrow-right" class="size-5" /></span>
                    <span wire:loading wire:target="login">Signing in…</span>
                </button>
            </form>
        @elseif (! $codeSent)
            <form wire:key="form-otp" wire:submit="sendCode" class="mt-8 space-y-5">
                <x-ui.field label="Email or mobile number" name="identifier" hint="We send a one-time code to your email or by SMS to your mobile.">
                    <div class="relative">
                        <x-ui.icon name="user" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-gym-muted" />
                        <input wire:model="identifier" id="identifier" type="text" autocomplete="username" autofocus class="input h-12 rounded-xl pl-12">
                    </div>
                </x-ui.field>
                @if (count($accounts) > 1)
                    <x-ui.field label="Sign in to" name="account">
                        <div class="space-y-2">
                            @foreach ($accounts as $a)
                                <label wire:key="acc-{{ $a['key'] }}" class="flex cursor-pointer items-center gap-3 rounded-xl border border-gym-border bg-gym-charcoal px-4 py-3 text-sm text-white transition has-checked:border-gym-orange has-checked:bg-gym-orange/10">
                                    <input wire:model="account" type="radio" value="{{ $a['key'] }}" class="border-gym-border bg-gym-black text-gym-orange focus:ring-gym-orange/40"> {{ $a['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </x-ui.field>
                @endif
                <button type="submit" class="btn-primary h-13 w-full rounded-full text-base" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="sendCode" class="inline-flex items-center gap-2">Send code <x-ui.icon name="arrow-right" class="size-5" /></span>
                    <span wire:loading wire:target="sendCode">Sending…</span>
                </button>
            </form>
        @else
            <form wire:key="form-code" wire:submit="verifyCode" class="mt-8 space-y-5">
                <p class="text-sm text-gym-muted">We sent a code to <strong class="text-white">{{ $this->sentTo() }}</strong>.@if ($account === \App\Services\OtpLoginService::STATUS) Enter it to see your registration status.@endif
                    <button type="button" wire:click="changeIdentifier" class="font-semibold text-gym-orange hover:underline">Change</button></p>
                <x-ui.field label="Code" name="code">
                    <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ config('gymai.otp.length', 6) }}" autofocus class="input h-14 rounded-xl text-center text-2xl font-semibold tracking-[0.5em]">
                </x-ui.field>
                <label class="flex items-center gap-2.5 text-sm text-gym-muted">
                    <input wire:model="remember" type="checkbox" class="{{ $checkbox }}"> Remember me
                </label>
                <button type="submit" class="btn-primary h-13 w-full rounded-full text-base" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="verifyCode">Verify & sign in</span>
                    <span wire:loading wire:target="verifyCode">Checking…</span>
                </button>
                <button type="button" wire:click="sendCode" class="w-full text-center text-sm font-medium text-gym-muted hover:text-gym-orange">Resend code</button>
            </form>
        @endif

        <div class="mt-8 flex items-center gap-4 text-xs font-semibold tracking-[0.2em] text-gym-muted uppercase">
            <span class="h-px flex-1 bg-gym-border"></span> New to Gymai? <span class="h-px flex-1 bg-gym-border"></span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('register.gym') }}" class="group flex items-center gap-3 rounded-xl border border-gym-border bg-gym-charcoal p-3 transition hover:border-gym-orange/60 hover:bg-gym-orange/5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gym-orange/15 text-gym-orange transition group-hover:bg-gym-orange group-hover:text-white"><x-ui.icon name="building" class="size-5" /></span>
                <span class="text-sm text-gym-muted">Own a gym? <span class="block font-semibold text-white">Register it free</span></span>
            </a>
            <a href="{{ route('member.join') }}" class="group flex items-center gap-3 rounded-xl border border-gym-border bg-gym-charcoal p-3 transition hover:border-gym-orange/60 hover:bg-gym-orange/5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gym-orange/15 text-gym-orange transition group-hover:bg-gym-orange group-hover:text-white"><x-ui.icon name="dumbbell" class="size-5" /></span>
                <span class="text-sm text-gym-muted">Gym member? <span class="block font-semibold text-white">Join your gym</span></span>
            </a>
        </div>
    </div>
</div>
