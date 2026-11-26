@php
    $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.').' kg';
    $history = collect($summary['history']);
    $reminder = $summary['reminder'];
    $popup = in_array($summary['popup']['type'] ?? null, ['goal_achieved', 'almost_there'], true) ? $summary['popup'] : null;
    $direction = $goal['direction'] ?? null;
    $chart = [
        'labels' => $history->map(fn ($h) => \Illuminate\Support\Carbon::parse($h['measured_on'])->format('d M'))->all(),
        'weights' => $history->pluck('weight_kg')->all(),
        'target' => $goal['target_weight_kg'] ?? null,
        'start' => $goal['start_weight_kg'] ?? null,
    ];
@endphp
<div class="pm-reveal card">
    <div class="card-header flex-wrap gap-3">
        <h2 class="card-title">Weight goal</h2>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="open('{{ $form === 'weight' ? '' : 'weight' }}')" @class(['btn-sm', 'btn-primary' => $form !== 'weight', 'btn-secondary' => $form === 'weight'])><x-ui.icon name="plus" class="size-4" /> Log weight</button>
            <button type="button" wire:click="open('{{ $form === 'goal' ? '' : 'goal' }}')" class="btn-secondary btn-sm"><x-ui.icon name="flag" class="size-4" /> {{ $goal && $goal['status'] === 'active' ? 'Change goal' : 'Set goal' }}</button>
        </div>
    </div>

    @if ($form === 'weight')
        <form wire:submit="record" class="card-body grid gap-3 border-b border-gray-100 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <x-ui.field label="Weight (kg)" name="weight"><input wire:model="weight" type="number" step="0.1" inputmode="decimal" class="input" autofocus></x-ui.field>
            <x-ui.field label="Date" name="measuredOn"><input wire:model="measuredOn" type="date" max="{{ now()->toDateString() }}" class="input"></x-ui.field>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save weight</button>
        </form>
    @elseif ($form === 'goal')
        <form wire:submit="setGoal" class="card-body grid gap-3 border-b border-gray-100 sm:grid-cols-3 sm:items-start">
            <x-ui.field label="Target weight (kg)" name="target"><input wire:model="target" type="number" step="0.1" inputmode="decimal" class="input" autofocus></x-ui.field>
            <x-ui.field label="By (optional)" name="targetDate"><input wire:model="targetDate" type="date" min="{{ now()->addDay()->toDateString() }}" class="input"></x-ui.field>
            <x-ui.field label="Current weight (kg)" name="weight" :hint="$summary['current_weight_kg'] ? 'Leave empty to use '.$kg($summary['current_weight_kg']).'.' : 'Needed to start your goal.'"><input wire:model="weight" type="number" step="0.1" inputmode="decimal" class="input"></x-ui.field>
            <div class="sm:col-span-3"><button type="submit" class="btn-primary" wire:loading.attr="disabled">Save goal</button> <span class="hint ml-2">Lower than now to lose weight, higher to gain.</span></div>
        </form>
    @endif

    <div class="card-body space-y-5">
        @if ($popup)
            <div class="flex items-start gap-3 rounded-xl bg-brand-600/10 px-4 py-3 text-sm">
                <x-ui.icon :name="$popup['type'] === 'goal_achieved' ? 'trophy' : 'flag'" class="mt-0.5 size-5 shrink-0 text-brand-600" />
                <p class="flex-1 text-gray-700"><strong class="text-gray-900">{{ $popup['title'] }}</strong> {{ $popup['message'] }}</p>
                <button type="button" wire:click="dismiss('{{ $popup['type'] }}')" class="text-gray-500 hover:text-gray-900" aria-label="Dismiss"><x-ui.icon name="x" class="size-4" /></button>
            </div>
        @endif

        @if ($goal)
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-2xl font-bold text-gray-900">
                        @if ($goal['status'] === 'achieved')
                            Goal reached: {{ $kg($goal['target_weight_kg']) }}
                        @else
                            {{ ['lose' => 'Lose', 'gain' => 'Gain', 'maintain' => 'Keep'][$direction] }} {{ $kg($goal['total_change_kg']) }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500">{{ $kg($goal['start_weight_kg']) }} → {{ $kg($goal['target_weight_kg']) }}@if ($goal['target_date']) by {{ \Illuminate\Support\Carbon::parse($goal['target_date'])->format('d M Y') }}@endif</p>
                </div>
                @if ($goal['status'] === 'active')
                    <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ $kg($goal['achieved_change_kg']) }}</strong> {{ $direction === 'gain' ? 'gained' : 'down' }}, <strong class="text-gray-900">{{ $kg($goal['remaining_kg']) }}</strong> to go</p>
                @endif
            </div>
            <div>
                <div class="h-2.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ (int) round($goal['progress_percent']) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress to your goal">
                    <div class="pm-bar-grow h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ (int) round($goal['progress_percent']) }}%"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ (int) round($goal['progress_percent']) }}% of the way.
                    @if ($pace && $pace['overdue'])
                        <span class="font-medium text-amber-700">The target date has passed. Set a new date to keep going.</span>
                    @elseif ($pace && $pace['on_track'])
                        <span class="font-medium text-emerald-700">On track for your date.</span>
                    @elseif ($pace)
                        <span class="font-medium text-amber-700">Behind your date: about {{ $kg($pace['kg_per_week']) }} a week needed now.</span>
                    @endif
                </p>
            </div>
        @elseif ($summary['current_weight_kg'])
            <p class="text-sm text-gray-600">You weigh <strong class="text-gray-900">{{ $kg($summary['current_weight_kg']) }}</strong>. Set a goal to see how close you are, whether you want to lose or gain weight.</p>
        @else
            <p class="text-sm text-gray-600">Log your weight, then set a goal to lose or gain. Your progress shows here.</p>
        @endif

        @if ($history->count() >= 2)
            <div wire:key="wg-chart-{{ md5(json_encode($chart)) }}" wire:ignore x-data x-init="renderChart($refs.c, {
                    type: 'line',
                    data: { labels: @js($chart['labels']), datasets: [
                        { label: 'Weight', data: @js($chart['weights']), borderColor: chartColors.primary, backgroundColor: chartColors.primarySoft, fill: true, tension: .35, pointRadius: 4, pointBackgroundColor: chartColors.primary },
                        ...(@js($chart['target']) ? [{ label: 'Goal', data: @js($chart['labels']).map(() => @js($chart['target'])), borderColor: chartColors.muted, borderDash: [6, 6], pointRadius: 0, fill: false }] : []),
                    ] },
                    options: { scales: { y: { beginAtZero: false, ticks: { callback: (v) => v + ' kg' } } } },
                })" class="h-56"><canvas x-ref="c" role="img" aria-label="Your weight over time{{ $chart['target'] ? ', with your goal of '.$kg($chart['target']) : '' }}"></canvas></div>
        @endif

        <p class="text-xs text-gray-500">
            @if (! $reminder['last_measured_on'])
                No weigh-ins yet.
            @elseif ($reminder['is_due'])
                <span class="font-medium text-amber-700">Weigh-in due</span>: last one on {{ \Illuminate\Support\Carbon::parse($reminder['last_measured_on'])->format('d M') }}.
            @else
                Last weigh-in {{ \Illuminate\Support\Carbon::parse($reminder['last_measured_on'])->format('d M') }}. Next one in {{ $reminder['days_until_due'] }} {{ \Illuminate\Support\Str::plural('day', $reminder['days_until_due']) }}.
            @endif
            Your gym sees your weigh-ins.
        </p>
    </div>
</div>
