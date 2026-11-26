@php
    $firstName = \Illuminate\Support\Str::of($member->name)->before(' ');
    $stateLabel = ['active' => 'Membership active', 'expired' => 'Membership expired', 'upcoming' => 'Starts soon', 'none' => 'No membership'][$state];
    $stateColor = ['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray';
    $daysLeft = $current?->daysRemaining();
    $used = $current && $current->duration_days ? (int) min(100, max(0, round(100 - $daysLeft / $current->duration_days * 100))) : 0;
    $renewSoon = $current && $daysLeft <= 7;
    $maxWeek = max(1, collect($weeklyVisits)->max('visits'));
    $weightChange = $weight['change_since_start_kg'];
@endphp
<div class="space-y-6">
    {{-- Welcome hero --}}
    <section class="pm-rise card relative isolate overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute -top-28 -right-20 -z-10 size-80 rounded-full bg-brand-600/25 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(rgb(255_255_255/0.03)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.03)_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:linear-gradient(to_left,black,transparent_70%)]"></div>
        <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-[1.25fr_1fr] lg:items-center">
            <div class="flex items-center gap-4 sm:gap-5">
                <div class="relative shrink-0">
                    <img src="{{ $member->photoUrl() }}" alt="" width="80" height="80" class="size-16 rounded-2xl object-cover ring-2 ring-brand-600/60 ring-offset-2 ring-offset-white sm:size-20 dark:ring-offset-ink-800">
                    @if ($state === 'active')<span class="absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full bg-emerald-500 text-white ring-2 ring-white dark:ring-ink-800"><x-ui.icon name="check" class="size-3" /></span>@endif
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-[0.18em] text-brand-600 uppercase">{{ $member->gym->name }}</p>
                    <h1 class="mt-1 text-2xl leading-tight font-bold tracking-tight text-gray-900 sm:text-3xl">Welcome back, {{ $firstName }}!</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-mono font-semibold text-gray-700">{{ $member->member_code }}</span>
                        <x-ui.badge :color="$stateColor">{{ $stateLabel }}</x-ui.badge>
                        @if ($current)<span>{{ $current->plan_name }} · ends {{ $current->end_date->format('d M Y') }}</span>@endif
                        @if ($member->trainer)<span class="inline-flex items-center gap-1"><x-ui.icon name="whistle" class="size-3.5 text-brand-600" /> {{ $member->trainer->name }}</span>@endif
                    </div>
                </div>
            </div>

            {{-- Today --}}
            @include('livewire.portal.partials.today', ['qrLink' => true])
        </div>

        @if ($renewSoon || in_array($state, ['expired', 'none'], true) || $pending > 0)
            <div class="flex flex-wrap items-center gap-3 border-t border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800 sm:px-7 dark:border-amber-500/20">
                <x-ui.icon name="bell" class="size-5 shrink-0" />
                <p class="flex-1">
                    @if (in_array($state, ['expired', 'none'], true))
                        You don't have an active membership. Visit the front desk to renew.
                    @elseif ($renewSoon)
                        Your <strong>{{ $current->plan_name }}</strong> plan ends in {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} ({{ $current->end_date->format('d M') }}). Renew at the front desk to keep training.
                    @endif
                    @if ($pending > 0) <strong>{{ money($pending) }}</strong> is still pending on your membership. @endif
                </p>
                <a href="{{ route('portal.membership') }}" class="font-semibold underline-offset-2 hover:underline">View membership →</a>
            </div>
        @endif
    </section>

    {{-- At a glance --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        @php
            $tiles = [
                ['Total attendance', $totalVisits, null, 'calendar', 'bg-indigo-50 text-indigo-600', $totalVisits === 1 ? 'visit so far' : 'visits so far', route('portal.check-in')],
                ['Current plan', null, $current?->plan_name ?? '—', 'card', 'bg-emerald-50 text-emerald-600', $current ? 'Since '.$current->start_date->format('d M Y') : 'No active plan', route('portal.membership')],
                ['Days remaining', $current ? $daysLeft : null, $current ? null : '—', 'clock', $renewSoon ? 'bg-amber-50 text-amber-600' : 'bg-sky-50 text-sky-600', $current ? 'Ends '.$current->end_date->format('d M') : 'Renew at the desk', route('portal.membership')],
                ['Workout sessions', $workoutsDone, null, 'dumbbell', 'bg-indigo-50 text-indigo-600', 'marked done in your program', route('portal.plans', 'workout')],
                ['This month', $visitsThisMonth, null, 'fire', 'bg-amber-50 text-amber-600', $streak['current_streak'].' day streak', route('portal.check-in')],
                ['Fitness progress', null, $weightChange === null ? '—' : ($weightChange > 0 ? '+' : '').rtrim(rtrim(number_format($weightChange, 1), '0'), '.').' kg', 'trending', 'bg-emerald-50 text-emerald-600', $weight['current_weight_kg'] ? 'Now '.rtrim(rtrim(number_format($weight['current_weight_kg'], 1), '0'), '.').' kg' : 'No weigh-ins yet', route('portal.progress')],
            ];
        @endphp
        @foreach ($tiles as $i => [$label, $count, $text, $icon, $tone, $hint, $href])
            <a href="{{ $href }}" class="pm-rise pm-tile card card-body flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-4" style="--d: {{ 60 + $i * 50 }}ms">
                <span class="pm-icon flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tone }}"><x-ui.icon :name="$icon" class="size-5" /></span>
                <span class="min-w-0">
                    <span class="block text-xs font-medium tracking-wide text-gray-500 uppercase">{{ $label }}</span>
                    <span class="mt-1 block truncate text-2xl font-bold text-gray-900" @if ($count !== null) data-count="{{ $count }}" @endif>{{ $count ?? $text }}</span>
                    <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $hint }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- Streak + attendance trend --}}
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="streak-card pm-reveal card card-body lg:col-span-3" x-data="streakCard" data-streak="{{ $streak['current_streak'] }}" data-state="{{ ! $streak['current_streak'] ? 'none' : ($streak['at_risk'] ? 'risk' : 'alive') }}" x-on:streak-grew.window="grew($event.detail)">
            <div class="flex items-center gap-4">
                <div wire:ignore class="streak-flame" :class="[state, flare ? 'flare' : '']" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="size-8" fill="currentColor">
                        <path class="streak-flame-outer" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                        <path class="streak-flame-inner" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z" />
                    </svg>
                    <template x-for="ember in embers" :key="ember.id"><span class="streak-ember" :style="ember.style"></span></template>
                </div>
                <div class="flex-1">
                    <p class="text-2xl font-bold text-gray-900"><span class="sr-only">{{ $streak['current_streak'] }} day streak</span><span aria-hidden="true"><span wire:ignore class="streak-count" :class="flare && 'flare'" x-text="shown">{{ $streak['current_streak'] }}</span> day streak</span></p>
                    <p class="text-sm text-gray-500">{{ $streak['visits_this_month'] }} visits this month · best {{ $streak['longest_streak'] }}</p>
                </div>
            </div>
            @if ($streak['at_risk'])<p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">Visit today to keep your streak alive!</p>@endif
            <p wire:ignore role="status"><span x-show="reached" x-cloak x-transition class="mt-4 flex items-center gap-2 rounded-lg bg-brand-600/10 px-3 py-2 text-sm font-semibold text-gray-900"><x-ui.icon name="trophy" class="size-5 text-brand-600" /> <span><span x-text="reached"></span> day milestone reached!</span></span></p>
            <x-streak.week :week="$streak['week']" class="mt-6" />
            @if ($streak['next_milestone'])
                @php($milestonePct = (int) round(($streak['next_milestone']['target'] - $streak['next_milestone']['remaining']) / max(1, $streak['next_milestone']['target']) * 100))
                <div class="mt-6">
                    <div class="flex items-center justify-between text-sm">
                        <p class="text-gray-500">{{ $streak['next_milestone']['remaining'] }} more visit days to your {{ $streak['next_milestone']['target'] }}-day milestone.</p>
                        <x-ui.icon name="trophy" class="size-5 shrink-0 text-brand-600" />
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100"><div class="streak-bar h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ $milestonePct }}%"></div></div>
                </div>
            @endif
        </div>

        <div class="pm-reveal card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Weekly visits</h2><span class="text-xs text-gray-500">Last 8 weeks</span></div>
            <div class="card-body">
                <div class="flex h-36 items-end gap-2" role="img" aria-label="Visits per week: {{ collect($weeklyVisits)->pluck('visits')->implode(', ') }}">
                    @foreach ($weeklyVisits as $i => $w)
                        <div class="group flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                            <span class="text-[10px] font-semibold text-gray-500 opacity-0 transition group-hover:opacity-100">{{ $w['visits'] }}</span>
                            <span @class(['pm-col-grow w-full rounded-md', 'bg-gradient-to-t from-brand-600 to-brand-400' => $loop->last, 'bg-indigo-200 group-hover:bg-indigo-300' => ! $loop->last])
                                  style="height: {{ max(4, round($w['visits'] / $maxWeek * 100)) }}%; --d: {{ $i * 60 }}ms"></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2 text-center text-[10px] text-gray-500">
                    @foreach ($weeklyVisits as $w)<span class="flex-1 truncate">{{ $w['label'] }}</span>@endforeach
                </div>
            </div>
        </div>
    </div>

    <livewire:portal.weight-goal />

    <div class="pm-reveal">
        @include('livewire.portal.partials.programs', ['programs' => $programs, 'types' => ['workout', 'diet'], 'detailed' => false])
    </div>

    {{-- Membership + payments snapshot --}}
    <div class="grid gap-6 lg:grid-cols-5">
        <a href="{{ route('portal.membership') }}" class="pm-reveal pm-tile card card-body lg:col-span-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="card-title">Current membership</h2>
                <x-ui.badge :color="$stateColor">{{ $stateLabel }}</x-ui.badge>
            </div>
            @if ($current)
                <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ $current->plan_name }}</p>
                        <p class="text-sm text-gray-500">{{ $current->start_date->format('d M Y') }} – {{ $current->end_date->format('d M Y') }}</p>
                    </div>
                    <p class="text-sm text-gray-600"><strong class="text-3xl font-bold text-brand-600">{{ $daysLeft }}</strong> days remaining</p>
                </div>
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-gray-100"><div class="pm-bar-grow h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ $used }}%"></div></div>
                <p class="mt-2 text-xs text-gray-500">{{ $used }}% of your plan used</p>
            @elseif ($latest && $state === 'upcoming')
                <p class="mt-4 text-sm text-gray-600">Your <strong>{{ $latest->plan_name }}</strong> starts on {{ $latest->start_date->format('d M Y') }}.</p>
            @else
                <p class="mt-4 text-sm text-gray-600">You don't have an active membership{{ $latest ? ' — your last one ended on '.$latest->end_date->format('d M Y') : '' }}. Please visit the front desk to renew.</p>
            @endif
        </a>
        <div class="pm-reveal card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Recent payments</h2><a href="{{ route('portal.membership') }}#payments" class="text-xs font-medium text-brand-600 hover:underline">View all</a></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($recentPayments as $p)
                    <li wire:key="rp-{{ $p->id }}" class="flex items-center gap-3 px-5 py-3 text-sm">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><x-ui.icon name="check" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900">{{ money($p->amount) }} <span class="font-normal text-gray-500">· {{ $p->method->label() }}</span></p>
                            <p class="truncate text-xs text-gray-500">{{ $p->paid_at->format('d M Y') }}</p>
                        </div>
                        <a href="{{ route('portal.receipt', $p) }}" target="_blank" class="text-xs font-medium text-brand-600 hover:underline">Receipt</a>
                    </li>
                @empty
                    <li><x-ui.empty message="No payments yet." icon="cash" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
