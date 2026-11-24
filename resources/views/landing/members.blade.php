@php
    // Member web portal + mobile app. [title, copy]. The phone screens use demo data.
    $appSteps = [
        ['Sign in with a code', 'No passwords to forget. Members sign in with a one-time code sent by SMS or email.'],
        ['Membership card', 'Plan, expiry and a personal QR code, always in their pocket.'],
        ['One-tap check-in', 'Members check themselves in from the app, or show their QR at the desk.'],
        ['Streaks & badges', 'Every visit grows a streak, so showing up becomes a habit.'],
        ['Weight goals', 'Log weight, set a goal and watch the trend move.'],
        ['Diet & workout programs', 'The plans their trainer assigned, day by day.'],
    ];
@endphp
<section id="members" data-lp-section data-lp-live class="relative border-y-2 border-forest bg-chalk-dim py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="lp-mono">Member web portal &amp; mobile app</p>
            <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">Your members get <span class="lp-hl">an app, too.</span></h2>
            <p class="mt-5 max-w-xl text-lg font-medium text-forest/80">Branded with your gym, included in the price. Members join, check in, track progress and follow their plans without calling the desk.</p>
        </div>

        <div class="mt-10 grid gap-10 lg:grid-cols-[1fr_22rem] lg:gap-16">
            <ol class="order-2 border-t-2 border-forest lg:order-1">
                @foreach ($appSteps as $i => [$title, $copy])
                    <li data-lp-app-row class="border-b-2 border-forest lg:min-h-[30vh]">
                        <button type="button" data-lp-app-step="{{ $i }}" aria-controls="lp-screen-{{ $i }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" class="lp-step flex w-full items-start gap-5 p-4 text-left sm:p-6">
                            <span class="lp-step-no lp-display text-3xl text-clay sm:text-4xl">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span>
                                <span class="lp-display block text-3xl sm:text-4xl">{{ $title }}</span>
                                <span class="mt-2 block max-w-md font-medium opacity-80">{{ $copy }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <div class="order-1 lg:order-2">
                <div class="lp-phone relative mx-auto h-[34rem] w-[17rem] overflow-hidden rounded-[2.6rem] border-[10px] border-forest bg-chalk lg:sticky lg:top-28" aria-hidden="true">
                    <span class="absolute top-2 left-1/2 z-10 h-5 w-20 -translate-x-1/2 rounded-full bg-forest"></span>

                    {{-- 1. OTP sign-in --}}
                    <div id="lp-screen-0" data-lp-app-screen="0" class="lp-screen is-active flex flex-col bg-chalk px-5 pt-12">
                        <p class="lp-mono">Iron Temple Fitness</p>
                        <p class="lp-display mt-3 text-4xl">Sign in</p>
                        <p class="mt-6 border-2 border-forest p-3 text-sm font-semibold">+91 98765 43210</p>
                        <p class="lp-mono mt-6">Enter the 6-digit code</p>
                        <div class="mt-2 grid grid-cols-6 gap-1.5">
                            @foreach ([4, 8, 1, 9, 2, 7] as $d => $digit)
                                <span class="flex h-11 items-center justify-center border-2 border-forest bg-white/40"><span class="lp-otp lp-display text-2xl" style="--i: {{ $d }}">{{ $digit }}</span></span>
                            @endforeach
                        </div>
                        <span class="mt-auto mb-8 bg-forest py-3 text-center font-bold text-volt">Verify</span>
                    </div>

                    {{-- 2. Membership card --}}
                    <div id="lp-screen-1" data-lp-app-screen="1" class="lp-screen flex flex-col bg-forest px-5 pt-12 text-chalk">
                        <p class="lp-mono text-volt">Membership</p>
                        <div class="mt-5 -rotate-2 bg-volt p-4 text-forest shadow-[6px_6px_0_0_var(--color-clay)]">
                            <p class="lp-mono">Iron Temple Fitness</p>
                            <p class="lp-display mt-3 text-3xl">Meera Iyer</p>
                            <div class="mt-4 flex items-end justify-between">
                                <p class="text-xs font-bold">GOLD · 12 MONTHS<br>Valid till 31 Mar 2027</p>
                                <span class="lp-qr size-14 border-2 border-forest bg-chalk"></span>
                            </div>
                        </div>
                        <p class="mt-8 text-sm">Member ID <strong class="text-volt">ITF-0412</strong></p>
                        <p class="mt-1 text-sm">Trainer <strong class="text-volt">Coach Asha</strong></p>
                    </div>

                    {{-- 3. Check-in --}}
                    <div id="lp-screen-2" data-lp-app-screen="2" class="lp-screen flex flex-col items-center bg-chalk px-5 pt-12">
                        <p class="lp-mono self-start">Check-in</p>
                        <span class="lp-ring-pulse relative mt-12 flex size-44 items-center justify-center rounded-full bg-forest text-volt">
                            <x-ui.icon name="qr" class="size-16" />
                        </span>
                        <p class="lp-display mt-10 text-3xl">Checked in</p>
                        <p class="mt-1 text-sm font-semibold text-forest/70">Today · 6:42 AM · 37 inside</p>
                    </div>

                    {{-- 4. Streak --}}
                    <div id="lp-screen-3" data-lp-app-screen="3" class="lp-screen flex flex-col bg-volt px-5 pt-12">
                        <p class="lp-mono">Your streak</p>
                        <div class="mt-8 flex items-center gap-3"><x-ui.icon name="fire" class="lp-flame size-20 text-clay" /><span class="lp-display text-8xl">21</span></div>
                        <p class="font-bold">days in a row</p>
                        <div class="mt-8 grid grid-cols-7 gap-1">
                            @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $day)
                                <span class="lp-mono flex h-9 items-center justify-center bg-forest text-volt">{{ $day }}</span>
                            @endforeach
                        </div>
                        <p class="mt-6 flex items-center gap-2 text-sm font-bold"><x-ui.icon name="trophy" class="size-5" /> Badge unlocked: 3 weeks strong</p>
                    </div>

                    {{-- 5. Weight goal --}}
                    <div id="lp-screen-4" data-lp-app-screen="4" class="lp-screen flex flex-col items-center bg-chalk px-5 pt-12">
                        <p class="lp-mono self-start">Weight goal</p>
                        <span class="mt-8 flex size-44 items-center justify-center rounded-full" style="background: conic-gradient(var(--color-forest) 0 72%, rgb(20 35 26 / 0.12) 0)">
                            <span class="flex size-34 flex-col items-center justify-center rounded-full bg-chalk"><span class="lp-display text-5xl">72%</span><span class="lp-mono">to goal</span></span>
                        </span>
                        <svg viewBox="0 0 220 60" class="mt-8 w-full" aria-hidden="true"><path d="M5 10 L40 16 L75 14 L110 28 L145 34 L180 44 L215 50" fill="none" stroke="var(--color-clay)" stroke-width="4" stroke-linejoin="round" /></svg>
                        <p class="lp-mono mt-2 flex w-full justify-between"><span>82.4 kg</span><span>76.1 kg</span></p>
                    </div>

                    {{-- 6. Programs --}}
                    <div id="lp-screen-5" data-lp-app-screen="5" class="lp-screen flex flex-col bg-chalk px-5 pt-12">
                        <p class="lp-mono">Today · push day</p>
                        <p class="lp-display mt-3 text-4xl">Workout</p>
                        <ul class="mt-5 space-y-2 text-sm font-semibold">
                            @foreach (['Bench press · 4 × 8', 'Incline DB press · 3 × 10', 'Shoulder press · 3 × 10', 'Triceps dips · 3 × 12'] as $p => $exercise)
                                <li class="flex items-center gap-3 border-2 border-forest p-2.5 {{ $p < 2 ? 'bg-forest text-volt' : '' }}"><x-ui.icon name="{{ $p < 2 ? 'check' : 'dumbbell' }}" class="size-4" /> {{ $exercise }}</li>
                            @endforeach
                        </ul>
                        <p class="lp-mono mt-5">Diet · 2,200 kcal · 4 meals</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
