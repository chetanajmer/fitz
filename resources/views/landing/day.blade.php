@php
    // "A day at your gym": what Fitnix does between opening and closing. [time, minutes after midnight, icon, text].
    // Names and figures are demo data. landing.js plays the day as the visitor scrolls.
    $events = [
        ['5:42 AM', 342, 'qr', 'Doors open. First QR check-in of the day.'],
        ['6:15 AM', 375, 'fire', 'Rohan hits a 21-day streak and unlocks a badge.'],
        ['7:30 AM', 450, 'receipt', '₹1,500 received by UPI. Receipt sent.'],
        ['9:00 AM', 540, 'bell', 'Renewal reminders pushed to 12 members.'],
        ['11:20 AM', 680, 'inbox', 'Walk-in enquiry saved, follow-up booked.'],
        ['1:00 PM', 780, 'dumbbell', 'Coach Asha updates three workout plans.'],
        ['4:45 PM', 1005, 'globe', 'A new member joins online from your gym page.'],
        ['6:30 PM', 1110, 'users', 'Evening rush: 37 members inside now.'],
        ['8:10 PM', 1210, 'sparkles', 'AI Coach report ready for Arjun.'],
        ['9:45 PM', 1305, 'chart', 'Day closed: ₹18,400 collected, zero registers.'],
    ];
    $last = count($events) - 1;
@endphp
<section data-lp-day data-lp-day-start="300" data-lp-day-end="1320" class="lp-dayline relative py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Fixed two-column header: the clock never changes width, so nothing below it moves while the day plays. --}}
        <div class="items-end gap-10 lg:grid lg:grid-cols-[1fr_auto]">
            <div>
                <p class="lp-mono">A day at your gym</p>
                <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">You coach.<br> <span class="lp-hl">Fitnix runs the desk.</span></h2>
            </div>
            <p class="hidden text-right lg:block" aria-hidden="true">
                <span data-lp-day-clock class="lp-display lp-clock block text-7xl whitespace-nowrap">
                    @foreach (mb_str_split('05:00 AM') as $char)<span class="lp-clock-ch {{ $char === ':' || $char === ' ' ? 'is-narrow' : '' }}">{{ $char }}</span>@endforeach
                </span>
                <span class="lp-mono">Scroll to play the day</span>
            </p>
        </div>

        <div class="relative mt-10 lg:mt-6 lg:h-[30rem]">
            {{-- Timeline (desktop): base line, the played part, and the "now" marker. --}}
            <div aria-hidden="true" class="absolute inset-x-0 top-1/2 hidden h-[3px] -translate-y-1/2 bg-forest/25 lg:block"></div>
            <div aria-hidden="true" data-lp-day-progress class="absolute inset-x-0 top-1/2 hidden h-[3px] origin-left -translate-y-1/2 scale-x-0 bg-forest lg:block"></div>
            <span aria-hidden="true" data-lp-day-marker class="absolute top-1/2 left-0 z-10 hidden size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-forest bg-clay lg:block"></span>

            @foreach ($events as $i => $event)
                <span aria-hidden="true" data-lp-event-dot class="lp-event-dot absolute top-1/2 hidden size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-forest bg-chalk lg:block" style="left: {{ round(4 + $i * 92 / $last, 2) }}%"></span>
            @endforeach

            <ol class="relative space-y-4 border-l-2 border-forest pl-6 lg:h-full lg:space-y-0 lg:border-l-0 lg:pl-0">
                @foreach ($events as $i => [$time, $minutes, $icon, $text])
                    @php($x = round(4 + $i * 92 / $last, 2))
                    <li data-lp-event data-x="{{ $x }}" data-minutes="{{ $minutes }}" class="lp-event {{ $i % 2 === 0 ? 'is-up' : 'is-down' }}" style="--x: {{ $x }}%">
                        <div class="lp-event-card relative border-2 border-forest bg-chalk p-3.5">
                            <span aria-hidden="true" class="absolute top-4 -left-[2.0625rem] size-3 rounded-full border-2 border-forest bg-volt lg:hidden"></span>
                            <p class="lp-mono flex items-center justify-between gap-2"><time>{{ $time }}</time><x-ui.icon :name="$icon" class="size-4 text-clay" /></p>
                            <p class="mt-1.5 text-sm leading-snug font-semibold">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
