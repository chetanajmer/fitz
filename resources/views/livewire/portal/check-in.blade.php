@php
    // Month calendar: blanks before the 1st (weeks start on Monday), then each day of the month.
    $daysInMonth = $monthStart->daysInMonth;
    $leading = $monthStart->dayOfWeekIso - 1;
    $today = now()->toDateString();
@endphp
<div class="space-y-6">
    <x-ui.page-header title="QR check-in" subtitle="Show this code at the front desk to check in or out." />

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- QR card --}}
        <section class="pm-rise card relative isolate overflow-hidden lg:col-span-2">
            <div aria-hidden="true" class="pointer-events-none absolute -top-20 left-1/2 -z-10 size-72 -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl"></div>
            <div class="card-body flex flex-col items-center text-center">
                <p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">{{ $member->gym->name }}</p>
                <div class="pm-qr group mt-5 rounded-3xl bg-white p-4 shadow-[0_20px_60px_-25px_color-mix(in_srgb,var(--gym-orange)_70%,transparent)] ring-1 ring-gray-200 transition duration-300 hover:scale-[1.02] dark:ring-ink-700">
                    <span aria-hidden="true" class="pm-corner top-2 left-2 rounded-tl-xl border-t-[3px] border-l-[3px]"></span>
                    <span aria-hidden="true" class="pm-corner top-2 right-2 rounded-tr-xl border-t-[3px] border-r-[3px]"></span>
                    <span aria-hidden="true" class="pm-corner bottom-2 left-2 rounded-bl-xl border-b-[3px] border-l-[3px]"></span>
                    <span aria-hidden="true" class="pm-corner right-2 bottom-2 rounded-br-xl border-r-[3px] border-b-[3px]"></span>
                    <img src="{{ route('portal.qr') }}" alt="My check-in QR code" width="240" height="240" class="size-56 sm:size-60">
                </div>
                <p class="mt-5 text-lg font-semibold text-gray-900">{{ $member->name }}</p>
                <p class="font-mono text-sm text-gray-500">{{ $member->member_code }}</p>
                <ol class="mt-5 w-full space-y-2 text-left text-sm text-gray-600">
                    @foreach (['Open this page at the front desk.', 'Hold the code up to the scanner (turn up your screen brightness).', 'Scan again when you leave to check out.'] as $i => $step)
                        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600">{{ $i + 1 }}</span>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>
        </section>

        <div class="space-y-6 lg:col-span-3">
            <div class="pm-rise" style="--d: 80ms">
                @include('livewire.portal.partials.today')
            </div>

            <div class="grid grid-cols-3 gap-4">
                @foreach ([['Total visits', $totalVisits, 'calendar'], ['This month', count($visitedThisMonth), 'fire'], ['Day streak', $streak['current_streak'], 'trophy']] as $i => [$label, $value, $icon])
                    <div class="pm-rise card card-body text-center" style="--d: {{ 120 + $i * 50 }}ms">
                        <x-ui.icon :name="$icon" class="mx-auto size-5 text-brand-600" />
                        <p class="mt-2 text-2xl font-bold text-gray-900" data-count="{{ $value }}">{{ $value }}</p>
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                    </div>
                @endforeach
            </div>

            {{-- This month --}}
            <div class="pm-rise card" style="--d: 260ms">
                <div class="card-header"><h2 class="card-title">{{ $monthStart->format('F Y') }}</h2><span class="text-xs text-gray-500">{{ count($visitedThisMonth) }} {{ \Illuminate\Support\Str::plural('visit', count($visitedThisMonth)) }}</span></div>
                <div class="card-body">
                    <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-medium text-gray-500">
                        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d)<span>{{ $d }}</span>@endforeach
                    </div>
                    <div class="mt-2 grid grid-cols-7 gap-1.5">
                        @for ($i = 0; $i < $leading; $i++)<span></span>@endfor
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $date = $monthStart->copy()->day($d)->toDateString();
                                $visited = in_array($date, $visitedThisMonth, true);
                            @endphp
                            <span @class([
                                'flex h-9 items-center justify-center rounded-lg text-xs font-semibold transition sm:h-10',
                                'bg-brand-600 text-white shadow-[0_6px_14px_-6px_color-mix(in_srgb,var(--gym-orange)_80%,transparent)]' => $visited,
                                'ring-2 ring-brand-500 text-gray-900' => ! $visited && $date === $today,
                                'bg-gray-50 text-gray-400' => ! $visited && $date !== $today,
                            ]) title="{{ \Illuminate\Support\Carbon::parse($date)->format('D, d M') }}{{ $visited ? ' · visited' : '' }}">{{ $d }}</span>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="pm-reveal card">
        <div class="card-header"><h2 class="card-title">Recent check-ins</h2><span class="text-xs text-gray-500">Last {{ $attendance->count() }}</span></div>
        <ul class="divide-y divide-gray-100">
            @forelse ($attendance as $a)
                <li wire:key="a-{{ $a->id }}" class="flex items-center gap-3 px-5 py-2.5 text-sm">
                    <span class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 leading-none text-indigo-600">
                        <span class="text-[10px] font-semibold uppercase">{{ $a->date->format('M') }}</span>
                        <span class="text-sm font-bold">{{ $a->date->format('d') }}</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-gray-900">{{ $a->date->format('l') }}</p>
                        <p class="text-xs text-gray-500">{{ ['qr' => 'QR scan', 'self' => 'Self check-in'][$a->method] ?? 'Front desk' }}{{ $a->check_out_at ? ' · '.$a->durationLabel() : '' }}</p>
                    </div>
                    <span class="text-gray-500">{{ $a->check_in_at->format('h:i A') }} – {{ $a->check_out_at?->format('h:i A') ?? '…' }}</span>
                </li>
            @empty
                <li><x-ui.empty message="No visits recorded yet." icon="calendar" /></li>
            @endforelse
        </ul>
    </div>
</div>
