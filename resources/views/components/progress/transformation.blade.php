{{-- First versus latest photos, weight and measurements. $data comes from TransformationService::summary(). --}}
@props(['data', 'photoUrl'])
@php
    $date = fn (?string $d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '—';
    $signed = fn ($v, $unit) => $v === null ? '—' : ($v > 0 ? '+' : '').$v.' '.$unit;
    $series = collect($data['measurements'])->where('field', '!=', 'body_fat_percent');
    $days = $series->flatMap(fn ($m) => array_column($m['history'], 'date'))->unique()->sort()->values();
    $colors = ['#ff6b00', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6'];
    $datasets = $series->values()->map(fn ($m, $i) => ['label' => $m['label'], 'borderColor' => $colors[$i % 5], 'backgroundColor' => $colors[$i % 5], 'spanGaps' => true, 'tension' => .3,
        'data' => $days->map(fn ($d) => collect($m['history'])->firstWhere('date', $d)['value'] ?? null)->all()]);
@endphp
<div class="card">
    <div class="card-header"><h2 class="card-title">Transformation</h2></div>
    <div class="card-body space-y-6">
        <div @class(['space-y-6', 'lg:grid lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-center lg:gap-8 lg:space-y-0' => count($data['photos']) === 1])>
        @if ($data['photos'])
            <div @class(['grid gap-4', 'sm:grid-cols-2 lg:grid-cols-3' => count($data['photos']) > 1, 'max-w-72' => count($data['photos']) === 1])>
                @foreach ($data['photos'] as $pair)
                    <div wire:key="pair-{{ $pair['before']->id }}-{{ $pair['after']->id }}">
                        <x-progress.before-after :before="$photoUrl($pair['before']->id)" :after="$photoUrl($pair['after']->id)" />
                        <p class="mt-2 text-center text-xs text-gray-500">{{ $pair['label'] }} · {{ $pair['before']->taken_on->format('d M Y') }} to {{ $pair['after']->taken_on->format('d M Y') }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="hint">Add two photos of the same pose, on different days, to compare them here.</p>
        @endif

        @if ($data['weight']['latest_kg'] !== null || $data['measurements'])
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th></th><th>First</th><th>Latest</th><th>Change</th></tr></thead>
                    <tbody>
                        @if ($data['weight']['latest_kg'] !== null)
                            <tr><td class="font-medium">Weight (kg)</td><td>{{ $data['weight']['first_kg'] }} <span class="text-xs text-gray-500">{{ $date($data['weight']['first_on']) }}</span></td><td>{{ $data['weight']['latest_kg'] }} <span class="text-xs text-gray-500">{{ $date($data['weight']['latest_on']) }}</span></td><td class="font-semibold">{{ $data['weight']['first_on'] === $data['weight']['latest_on'] ? '—' : $signed($data['weight']['change_kg'], 'kg') }}</td></tr>
                        @endif
                        @foreach ($data['measurements'] as $m)
                            <tr wire:key="tm-{{ $m['field'] }}"><td class="font-medium">{{ $m['label'] }}</td><td>{{ $m['first'] }} <span class="text-xs text-gray-500">{{ $date($m['first_on']) }}</span></td><td>{{ $m['latest'] }} <span class="text-xs text-gray-500">{{ $date($m['latest_on']) }}</span></td><td class="font-semibold">{{ $signed($m['change'], $m['field'] === 'body_fat_percent' ? '%' : 'cm') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        </div>

        @if (count($data['weight']['history']) > 1)
            <div><p class="mb-2 text-sm font-semibold">Weight (kg)</p>
                <div class="h-56" wire:key="tw-{{ md5(json_encode($data['weight']['history'])) }}" x-data x-init="renderChart($refs.c, { type: 'line', data: { labels: @js(array_map(fn ($h) => \Illuminate\Support\Carbon::parse($h['date'])->format('d M'), $data['weight']['history'])), datasets: [{ label: 'Weight (kg)', data: @js(array_column($data['weight']['history'], 'value')), borderColor: chartColors.primary, backgroundColor: chartColors.primarySoft, fill: true, tension: .3 }] }, options: { scales: { y: { beginAtZero: false } } } })"><canvas x-ref="c"></canvas></div></div>
        @endif
        @if ($days->count() > 1)
            <div><p class="mb-2 text-sm font-semibold">Measurements (cm)</p>
                <div class="h-56" wire:key="tc-{{ md5(json_encode($datasets)) }}" x-data x-init="renderChart($refs.c, { type: 'line', data: { labels: @js($days->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))), datasets: @js($datasets) }, options: { scales: { y: { beginAtZero: false } } } })"><canvas x-ref="c"></canvas></div></div>
        @endif
    </div>
</div>
