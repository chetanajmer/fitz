@php
    $goal = $summary['goal'];
    $reminder = $summary['reminder'];
    $history = collect($summary['history']);
    $fmt = fn ($kg) => $kg === null ? '—' : rtrim(rtrim(number_format($kg, 2), '0'), '.').' kg';
    $signed = fn ($kg) => $kg === null ? '—' : ($kg > 0 ? '+' : '').rtrim(rtrim(number_format($kg, 2), '0'), '.').' kg';
@endphp
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Current weight" :value="$fmt($summary['current_weight_kg'])" icon="user"
                   :hint="$summary['last_measured_on'] ? 'Measured '.\Illuminate\Support\Carbon::parse($summary['last_measured_on'])->format('d M Y') : 'No weigh-in yet'" />
        <x-ui.stat label="Change since start" :value="$signed($summary['change_since_start_kg'])" icon="chart" color="blue"
                   :hint="$summary['first_measured_on'] ? 'From '.$fmt($summary['first_weight_kg']).' on '.\Illuminate\Support\Carbon::parse($summary['first_measured_on'])->format('d M Y') : '—'" />
        <x-ui.stat label="BMI" :value="$summary['bmi']['value'] ?? '—'" icon="calendar" :color="($summary['bmi']['category'] ?? '') === 'Normal' ? 'green' : 'amber'"
                   :hint="$summary['bmi'] ? $summary['bmi']['category'].' · height '.$summary['height_cm'].' cm' : 'Add height on the member profile'" />
        <x-ui.stat label="Next weigh-in" :value="$reminder['is_due'] ? 'Due now' : \Illuminate\Support\Carbon::parse($reminder['next_due_on'])->format('d M')" icon="clock"
                   :color="$reminder['is_due'] ? 'red' : 'green'" :hint="'Every '.$reminder['every_days'].' days'.($reminder['overdue_days'] ? ' · '.$reminder['overdue_days'].' days overdue' : '')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Goal --}}
            <div class="card card-body">
                @if ($goal)
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Target · {{ $goal['direction'] === 'gain' ? 'Gain' : 'Lose' }} {{ $fmt($goal['total_change_kg']) }}</p>
                            <p class="mt-1 text-2xl font-bold"><span class="inline-flex items-center gap-2">{{ $fmt($goal['start_weight_kg']) }} <x-ui.icon name="arrow-right" class="size-5 text-gray-400" /> {{ $fmt($goal['target_weight_kg']) }}</span></p>
                            <p class="text-sm text-gray-500">Started {{ \Illuminate\Support\Carbon::parse($goal['start_date'])->format('d M Y') }}@if ($goal['target_date']) · target date {{ \Illuminate\Support\Carbon::parse($goal['target_date'])->format('d M Y') }}@endif · set by {{ $goal['set_by'] === 'staff' ? 'gym' : 'member' }}</p>
                        </div>
                        @if ($goal['status'] === 'achieved')
                            <x-ui.badge color="green"><x-ui.icon name="trophy" class="mr-1 inline size-3.5" />Achieved {{ \Illuminate\Support\Carbon::parse($goal['achieved_at'])->format('d M Y') }}</x-ui.badge>
                        @elseif ($goal['is_near_target'])
                            <x-ui.badge color="amber"><x-ui.icon name="bolt" class="mr-1 inline size-3.5" />Almost there</x-ui.badge>
                        @else
                            <x-ui.badge color="blue">In progress</x-ui.badge>
                        @endif
                    </div>
                    <div class="mt-4">
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $goal['status'] === 'achieved' ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width: {{ $goal['progress_percent'] }}%"></div></div>
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="text-gray-600">{{ $fmt($goal['achieved_change_kg']) }} {{ $goal['direction'] === 'gain' ? 'gained' : 'lost' }} · {{ $goal['progress_percent'] }}%</span>
                            <span class="font-medium">{{ $goal['status'] === 'achieved' ? 'Target reached' : $fmt($goal['remaining_kg']).' to go' }}</span>
                        </div>
                    </div>
                @else
                    <x-ui.empty message="No weight target yet. Set one below — the member sees it in their app." icon="chart" />
                @endif
            </div>

            {{-- Chart --}}
            <div class="card">
                <div class="card-header"><h2 class="card-title">Weight history</h2><span class="text-xs text-gray-500">{{ $history->count() }} readings</span></div>
                @if ($history->count() >= 2)
                    <div class="card-body">
                        <div class="h-64" wire:key="wchart-{{ $history->pluck('id')->implode('-') }}-{{ $goal['target_weight_kg'] ?? 0 }}" x-data
                             x-init="renderChart($refs.c, { type: 'line', data: { labels: @js($history->map(fn ($h) => \Illuminate\Support\Carbon::parse($h['measured_on'])->format('d M'))), datasets: [
                                 { label: 'Weight (kg)', data: @js($history->pluck('weight_kg')), borderColor: chartColors.primary, backgroundColor: chartColors.primarySoft, fill: true, tension: .3, pointRadius: 4 },
                                 @if ($goal) { label: 'Target', data: @js($history->map(fn () => $goal['target_weight_kg'])), borderColor: chartColors.muted, borderDash: [6, 6], pointRadius: 0, fill: false }, @endif
                             ] }, options: { scales: { y: { beginAtZero: false } } } })"><canvas x-ref="c"></canvas></div>
                    </div>
                @endif
                <div class="max-h-80 overflow-y-auto">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Weight</th><th>Change</th><th>By</th><th>Note</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($history->reverse()->values() as $i => $h)
                                @php $prev = $history->reverse()->values()->get($i + 1); @endphp
                                <tr wire:key="wl-{{ $h['id'] }}">
                                    <td>{{ \Illuminate\Support\Carbon::parse($h['measured_on'])->format('d M Y') }}</td>
                                    <td class="font-semibold">{{ $fmt($h['weight_kg']) }}</td>
                                    <td class="text-gray-600">{{ $prev ? $signed(round($h['weight_kg'] - $prev['weight_kg'], 2)) : '—' }}</td>
                                    <td><x-ui.badge :color="$h['source'] === 'app' ? 'blue' : 'gray'">{{ $h['source'] === 'app' ? 'Member app' : 'Gym' }}</x-ui.badge></td>
                                    <td class="text-gray-500">{{ $h['note'] ?: '—' }}</td>
                                    <td class="text-right"><button wire:click="deleteLog({{ $h['id'] }})" wire:confirm="Delete this reading?" class="text-xs font-medium text-red-600 hover:underline">Delete</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ui.empty message="No weigh-ins yet." icon="chart" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <form wire:submit="record" class="card card-body space-y-4">
                <h2 class="card-title">Record weigh-in</h2>
                <x-ui.field label="Weight (kg)" name="weight" required><input wire:model="weight" type="number" step="0.1" min="20" max="350" class="input" placeholder="e.g. 72.5"></x-ui.field>
                <x-ui.field label="Date" name="measuredOn" required hint="Same date again corrects that reading."><input wire:model="measuredOn" type="date" max="{{ now()->toDateString() }}" class="input"></x-ui.field>
                <x-ui.field label="Note" name="note"><input wire:model="note" class="input" placeholder="Optional"></x-ui.field>
                <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="record">Save weight</button>
            </form>

            <form wire:submit="setGoal" class="card card-body space-y-4">
                <h2 class="card-title">{{ $goal && $goal['status'] === 'active' ? 'Change target' : 'Set a target' }}</h2>
                @if (! $summary['current_weight_kg'])
                    <x-ui.field label="Current weight (kg)" name="weight" required><input wire:model="weight" type="number" step="0.1" class="input"></x-ui.field>
                @endif
                <x-ui.field label="Target weight (kg)" name="target" required><input wire:model="target" type="number" step="0.1" min="20" max="350" class="input"></x-ui.field>
                <x-ui.field label="Target date" name="targetDate" hint="Optional"><input wire:model="targetDate" type="date" min="{{ now()->addDay()->toDateString() }}" class="input"></x-ui.field>
                <button type="submit" class="btn-secondary w-full" wire:loading.attr="disabled" wire:target="setGoal">Save target</button>
                <p class="text-xs text-gray-500">Progress starts from the latest weigh-in. When the target is reached the member gets a "Target achieved" popup in the app.</p>
            </form>
        </div>
    </div>
</div>
