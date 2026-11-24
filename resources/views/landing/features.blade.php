@php
    // Gym panel tour. [key, icon, title, copy, card style]. The little screens on each card use demo data.
    $modules = [
        ['members', 'users', 'Members', 'Profiles, goals, trainer assignment and full history. Bulk import from CSV or Excel.', 'chalk'],
        ['plans', 'card', 'Membership plans', 'Monthly to yearly or custom plans, with discounts, renewals and expiry tracking.', 'volt'],
        ['qr', 'qr', 'QR check-in', 'Members scan in, staff scan them, or check in by hand. Live "inside now" count.', 'clay'],
        ['payments', 'receipt', 'Fees & receipts', 'Cash, UPI, card or bank, full or part payments. Receipts and invoices in one tap.', 'outline'],
        ['streaks', 'fire', 'Streaks', 'Every check-in grows a streak, so members keep coming back and renewing.', 'volt'],
        ['weight', 'scale', 'Weight tracking', 'Members log weight and set a goal. You both see the trend.', 'chalk'],
        ['programs', 'dumbbell', 'Diet & workout plans', 'Build programs section by section or start from the ready-made library.', 'clay'],
        ['trainers', 'whistle', 'Trainers', 'Trainer logins that only show the members assigned to them.', 'outline'],
        ['enquiries', 'inbox', 'Enquiries', 'Capture walk-ins and calls, schedule follow-ups, convert in a click.', 'chalk'],
        ['push', 'bell', 'Push notifications', 'Send renewal reminders and announcements straight to members\' phones.', 'volt'],
        ['ai', 'sparkles', 'AI Coach', 'Progress reports and photo comparisons, written for every member.', 'clay'],
        ['reports', 'chart', 'Reports & billing', 'Revenue, attendance, renewals and pending fees. Clear monthly Gymai bill.', 'outline'],
    ];
    $cardStyles = [
        'chalk' => 'bg-chalk text-forest',
        'volt' => 'bg-volt text-forest',
        'clay' => 'bg-clay text-forest',
        'outline' => 'border-2 border-chalk/30 bg-forest-soft text-chalk',
    ];
@endphp
<section id="features" data-lp-section data-lp-tour data-lp-live class="lp-tour relative overflow-hidden bg-forest py-16 text-chalk lg:flex lg:min-h-screen lg:flex-col lg:justify-center lg:py-12">
    <div class="mx-auto flex w-full max-w-7xl flex-wrap items-end justify-between gap-6 px-4 sm:px-6 lg:px-8">
        <div>
            <p class="lp-mono text-volt">The gym panel</p>
            <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl lg:text-8xl">14 modules. <span class="text-volt">Zero registers.</span></h2>
        </div>
        <p class="lp-mono flex items-center gap-3 text-chalk/70">
            <span><span data-lp-tour-count class="text-volt">01</span> / {{ str_pad(count($modules), 2, '0', STR_PAD_LEFT) }}</span>
            <span class="hidden lg:inline">Keep scrolling →</span><span class="lg:hidden">Swipe →</span>
        </p>
    </div>

    <div class="lp-tour-viewport mt-10">
        <ol data-lp-track class="lp-track flex w-max gap-5 px-4 pb-6 sm:px-6 lg:gap-7 lg:px-[max(2rem,calc((100vw-80rem)/2+2rem))]">
            @foreach ($modules as $i => [$key, $icon, $title, $copy, $style])
                <li data-lp-card class="lp-card flex h-[28rem] w-[18.5rem] shrink-0 flex-col p-6 sm:w-[21rem] {{ $cardStyles[$style] }}">
                    <div class="flex items-center justify-between">
                        <span class="lp-mono">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <x-ui.icon :name="$icon" class="size-6" />
                    </div>
                    <h3 class="lp-display mt-4 text-4xl">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-relaxed font-medium opacity-80">{{ $copy }}</p>

                    <div class="lp-vis mt-auto h-40" aria-hidden="true">
                        @switch($key)
                            @case('members')
                                <div class="lp-rows">
                                    @foreach (['Aarav Shah' => 'Gold', 'Priya Nair' => 'Monthly', 'Kabir Singh' => 'Yearly', 'Meera Iyer' => 'Gold', 'Ishaan Rao' => 'Monthly', 'Ananya Das' => 'Yearly', 'Vikram Joshi' => 'Gold'] as $name => $plan)
                                        <div class="flex h-9 items-center gap-2.5 border-b border-forest/20 px-2.5 text-xs font-semibold">
                                            <span class="flex size-6 items-center justify-center bg-forest text-[10px] text-volt">{{ Str::of($name)->explode(' ')->map(fn ($part) => $part[0])->join('') }}</span>
                                            {{ $name }}
                                            <span class="ml-auto bg-volt px-1.5 py-0.5 text-[10px]">{{ $plan }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @break
                            @case('plans')
                                <div class="flex h-full flex-col justify-between p-3.5">
                                    <div class="flex items-start justify-between"><span class="lp-display text-3xl">Gold</span><span class="lp-mono">12 months</span></div>
                                    <div class="border-t-2 border-dashed border-forest/50 pt-3">
                                        <div class="lp-mono flex justify-between"><span>Valid till 31 Mar</span><span>Renew</span></div>
                                        <div class="mt-2 h-2.5 bg-forest/15"><div class="lp-fill h-full bg-forest"></div></div>
                                    </div>
                                </div>
                                @break
                            @case('qr')
                                <div class="lp-qr absolute inset-3 opacity-80"></div>
                                <span class="lp-scanline"></span>
                                <span class="lp-mono absolute right-2 bottom-2 bg-forest px-2 py-1 text-volt">Inside now · 37</span>
                                @break
                            @case('payments')
                                <div class="lp-receipt absolute inset-x-6 top-0 bg-chalk p-3 text-forest">
                                    <p class="lp-mono">Receipt #0482</p>
                                    <div class="mt-2 space-y-1.5"><div class="h-1.5 w-3/4 bg-forest/25"></div><div class="h-1.5 w-1/2 bg-forest/25"></div><div class="h-1.5 w-2/3 bg-forest/25"></div></div>
                                    <p class="mt-3 flex items-end justify-between border-t border-dashed border-forest/40 pt-2"><span class="lp-mono">UPI</span><span class="lp-display text-2xl">₹1,500</span></p>
                                </div>
                                @break
                            @case('streaks')
                                <div class="flex h-full flex-col justify-between p-3.5">
                                    <div class="flex items-center gap-2"><x-ui.icon name="fire" class="lp-flame size-9 text-clay" /><span class="lp-display text-5xl">21</span><span class="lp-mono leading-tight">day<br>streak</span></div>
                                    <div class="grid grid-cols-7 gap-1">
                                        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d => $day)
                                            <span class="lp-day lp-mono flex h-8 items-center justify-center border-2 border-forest" style="--i: {{ $d }}">{{ $day }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                @break
                            @case('weight')
                                <svg viewBox="0 0 300 150" class="size-full" preserveAspectRatio="none">
                                    <line x1="0" y1="112" x2="300" y2="112" stroke="currentColor" stroke-width="2" stroke-dasharray="6 6" opacity="0.5" />
                                    <path pathLength="1" class="lp-draw-loop" d="M10 30 L50 42 L90 38 L130 60 L170 70 L210 84 L250 96 L290 108" fill="none" stroke="var(--color-clay)" stroke-width="5" stroke-linejoin="round" stroke-linecap="round" />
                                </svg>
                                <span class="lp-mono absolute top-2 left-2">82.4 kg</span><span class="lp-mono absolute right-2 bottom-1">Goal 76 kg</span>
                                @break
                            @case('programs')
                                <ul class="space-y-2 p-3.5 text-xs font-semibold">
                                    @foreach (['Squats · 4 × 10', 'Bench press · 4 × 8', 'Oats + whey · 8 AM', 'Deadlift · 3 × 6'] as $t => $item)
                                        <li class="flex items-center gap-2.5"><span class="flex size-5 items-center justify-center border-2 border-forest"><x-ui.icon name="check" class="lp-tick size-3.5" style="--i: {{ $t }}" /></span>{{ $item }}</li>
                                    @endforeach
                                </ul>
                                @break
                            @case('trainers')
                                <div class="p-3.5 text-xs font-semibold">
                                    <p class="lp-mono text-volt">Coach Asha's view</p>
                                    @foreach (['Aarav Shah', 'Meera Iyer', 'Ishaan Rao'] as $t => $name)
                                        <p class="mt-2 flex items-center justify-between border-b border-chalk/20 pb-1.5">{{ $name }} <x-ui.icon name="shield" class="lp-lock size-4 text-volt" style="--i: {{ $t }}" /></p>
                                    @endforeach
                                </div>
                                @break
                            @case('enquiries')
                                <div class="grid h-full grid-cols-3">
                                    @foreach (['New', 'Follow-up', 'Joined'] as $column)
                                        <div class="lp-mono border-r border-forest/25 p-2 last:border-r-0">{{ $column }}</div>
                                    @endforeach
                                </div>
                                <span class="lp-lane absolute top-12 w-[22%] bg-forest p-2 text-[10px] font-bold text-volt">Riya · walk-in</span>
                                @break
                            @case('push')
                                <div class="space-y-2 p-3">
                                    @foreach (['Your plan renews in 3 days', 'Gym closed on Sunday for Diwali'] as $n => $note)
                                        <div class="lp-drop flex items-center gap-2 bg-chalk p-2 text-xs font-semibold" style="--i: {{ $n }}"><x-ui.icon name="bell" class="size-4 shrink-0 text-clay" /> {{ $note }}</div>
                                    @endforeach
                                </div>
                                @break
                            @case('ai')
                                <div class="flex h-full items-end gap-2 p-3.5">
                                    <div class="lp-bars flex h-full flex-1 items-end gap-1.5">
                                        @foreach ([40, 55, 50, 70, 78, 92] as $b => $height)
                                            <span class="flex-1 bg-forest" style="height: {{ $height }}%; --i: {{ $b }}"></span>
                                        @endforeach
                                    </div>
                                    <span class="lp-mono bg-chalk px-2 py-1">Report ready</span>
                                </div>
                                @break
                            @case('reports')
                                <div class="flex h-full flex-col p-3.5">
                                    <p class="flex items-baseline justify-between"><span class="lp-mono text-volt">Revenue · Nov</span><span class="lp-display text-2xl">₹2.4L</span></p>
                                    <div class="lp-bars mt-2 flex flex-1 items-end gap-1.5">
                                        @foreach ([35, 50, 42, 66, 58, 80, 95] as $b => $height)
                                            <span class="flex-1 bg-volt" style="height: {{ $height }}%; --i: {{ $b }}"></span>
                                        @endforeach
                                    </div>
                                </div>
                                @break
                        @endswitch
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
