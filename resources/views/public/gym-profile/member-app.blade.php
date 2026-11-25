{{-- How joining works and what members get in the Gymai app; the phone shows a labelled preview, not real data. --}}
@php
    $joinSteps = [
        ['search', 'Join online', 'Find '.$gym->name.' on Gymai and send your details.'],
        ['check', 'Gym approves you', 'The gym confirms your registration and you get your member ID.'],
        ['cash', 'Choose your plan', 'Pick a membership plan and pay at the front desk.'],
        ['device', 'Train with the app', 'Sign in with your mobile number: QR check-ins, workouts, diet plans and progress.'],
    ];
    $appFeatures = [
        ['qr', 'QR check-in'], ['dumbbell', 'Workout plans'], ['meal', 'Diet plans'], ['scale', 'Weight & progress'], ['fire', 'Attendance streaks'], ['receipt', 'Fees & receipts'],
    ];
@endphp
<section id="join" class="gp-section relative overflow-hidden">
    <div class="mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        <div>
            <p class="gp-reveal gp-eyebrow">How to join</p>
            <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Four steps to <span class="text-gym-orange">your first rep.</span></h2>

            <ol class="relative mt-10 space-y-7 before:absolute before:top-2 before:bottom-2 before:left-6 before:w-px before:bg-gradient-to-b before:from-gym-orange before:to-gym-border">
                @foreach ($joinSteps as $i => [$icon, $title, $text])
                    <li class="gp-reveal relative flex gap-5" style="--d: {{ $i * 100 }}ms">
                        <span class="relative z-10 flex size-12 shrink-0 items-center justify-center rounded-2xl border border-gym-orange/40 bg-gym-black text-gym-orange">
                            <x-ui.icon :name="$icon" class="size-5" />
                        </span>
                        <div class="pt-1">
                            <p class="text-xs font-semibold text-gym-orange">Step {{ $i + 1 }}</p>
                            <h3 class="mt-0.5 text-lg font-semibold text-white">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-gym-muted">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <a href="{{ $joinUrl }}" class="gp-reveal gp-btn gp-btn-solid mt-10">Start step 1 <x-ui.icon name="arrow-right" class="size-5" /></a>
        </div>

        {{-- Phone mock-up --}}
        <div class="relative mx-auto w-full max-w-sm">
            <div aria-hidden="true" class="absolute inset-x-6 top-10 bottom-0 -z-10 rounded-[3rem] bg-gym-orange/25 blur-[80px]"></div>
            <div class="gp-reveal relative rounded-[2.75rem] border border-white/10 bg-gym-black p-3 shadow-2xl shadow-black/60">
                <div class="overflow-hidden rounded-[2.2rem] bg-gym-black-deep">
                    <div class="relative h-48">
                        <img src="{{ asset('images/gym-page/member.webp') }}" alt="" loading="lazy" decoding="async" width="736" height="981" class="size-full object-cover object-[35%_20%] grayscale">
                        <div class="absolute inset-0 bg-gradient-to-t from-gym-black-deep via-gym-black-deep/30 to-transparent"></div>
                        <span class="absolute top-4 left-1/2 h-5 w-24 -translate-x-1/2 rounded-full bg-black"></span>
                        <span class="absolute top-4 right-4 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-gym-light uppercase">Preview</span>
                        <div class="absolute inset-x-5 bottom-3">
                            <p class="text-xs text-gym-muted">Member at</p>
                            <p class="truncate text-lg font-semibold text-white">{{ $gym->name }}</p>
                        </div>
                    </div>
                    <div class="space-y-3 p-5">
                        <div class="flex items-center gap-3 rounded-2xl bg-gym-card p-3">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-gym-orange text-white"><x-ui.icon name="qr" class="size-6" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-white">Check in</p>
                                <p class="text-xs text-gym-muted">Scan at the front desk</p>
                            </div>
                            <x-ui.icon name="arrow-right" class="size-4 text-gym-muted" />
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (array_slice($appFeatures, 1, 3) as [$icon, $label])
                                <div class="rounded-2xl bg-gym-card p-3 text-center">
                                    <x-ui.icon :name="$icon" class="mx-auto size-5 text-gym-orange" />
                                    <p class="mt-1.5 text-[11px] leading-tight text-gym-light">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="rounded-2xl bg-gym-card p-4">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-white">This week</span>
                                <span class="text-gym-orange">Sample</span>
                            </div>
                            <div class="mt-3 flex h-16 items-end gap-1.5" aria-hidden="true">
                                @foreach ([40, 70, 55, 90, 35, 80, 60] as $h)
                                    <span class="flex-1 rounded-md bg-gradient-to-t from-gym-orange to-gym-orange-light" style="height: {{ $h }}%"></span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="mt-8 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($appFeatures as $i => [$icon, $label])
                    <li class="gp-reveal flex items-center gap-2 rounded-xl border border-gym-border bg-gym-card/60 px-3 py-2.5 text-xs font-medium text-gym-light" style="--d: {{ $i * 60 }}ms">
                        <x-ui.icon :name="$icon" class="size-4 shrink-0 text-gym-orange" /> {{ $label }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
