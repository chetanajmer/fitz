{{--
    Fitness progress analytics from real data only: weight log history (with the goal line) and visits per
    week / month. Charts use the Chart.js already in app.js; the range and view switches filter client-side.
    $weight     WeightTrackingService::summary()
    $visits     ['weekly' => [[label, count]…], 'monthly' => [[label, count]…]]
--}}
@php
    $history = collect($weight['history']);
    $goal = $weight['goal'];
    $points = $history->map(fn ($h) => ['d' => $h['measured_on'], 'kg' => $h['weight_kg']])->values();
@endphp
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card">
        <div class="card-header flex-wrap gap-3">
            <div>
                <h2 class="card-title">Weight</h2>
                @if ($weight['current_weight_kg'])
                    <p class="mt-0.5 text-xs text-gray-500">
                        Now <strong class="text-gray-900">{{ rtrim(rtrim(number_format($weight['current_weight_kg'], 1), '0'), '.') }} kg</strong>
                        @if ($weight['change_since_start_kg'] !== null) · {{ $weight['change_since_start_kg'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($weight['change_since_start_kg'], 1), '0'), '.') }} kg since {{ \Illuminate\Support\Carbon::parse($weight['first_measured_on'])->format('d M') }}@endif
                        @if ($weight['bmi']) · BMI {{ $weight['bmi'] }}@endif
                    </p>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if ($points->count() >= 2)
                <div wire:ignore x-data="{
                        range: 'all',
                        points: @js($points),
                        target: @js($goal['target_weight_kg'] ?? null),
                        chart: null,
                        view() {
                            const months = { '3m': 3, '6m': 6 }[this.range];
                            if (! months) return this.points;
                            const from = new Date(); from.setMonth(from.getMonth() - months);
                            const shown = this.points.filter(p => new Date(p.d) >= from);
                            return shown.length >= 2 ? shown : this.points.slice(-2);
                        },
                        draw() {
                            const pts = this.view();
                            const labels = pts.map(p => new Date(p.d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }));
                            const datasets = [{ label: 'Weight (kg)', data: pts.map(p => p.kg), borderColor: chartColors.primary, backgroundColor: chartColors.primarySoft, fill: true, tension: .35, pointRadius: 4, pointBackgroundColor: chartColors.primary }];
                            if (this.target) datasets.push({ label: 'Goal', data: pts.map(() => this.target), borderColor: chartColors.muted, borderDash: [6, 6], pointRadius: 0, fill: false });
                            this.chart = renderChart(this.$refs.c, { type: 'line', data: { labels, datasets }, options: { scales: { y: { beginAtZero: false } } } });
                        },
                    }" x-init="draw()">
                    <div class="mb-3 inline-flex rounded-full border border-gray-200 p-0.5 text-xs font-medium dark:border-ink-700" role="group" aria-label="Date range">
                        @foreach (['3m' => '3 months', '6m' => '6 months', 'all' => 'All'] as $key => $label)
                            <button type="button" x-on:click="range = '{{ $key }}'; draw()" :class="range === '{{ $key }}' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:text-gray-900'" class="rounded-full px-3 py-1 transition">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="h-56"><canvas x-ref="c" aria-label="Weight over time"></canvas></div>
                </div>
                @if ($goal)
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs text-gray-500"><span>Goal {{ rtrim(rtrim(number_format($goal['target_weight_kg'], 1), '0'), '.') }} kg</span><span>{{ (int) round($goal['progress_percent']) }}%</span></div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100"><div class="pm-bar-grow h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ (int) round($goal['progress_percent']) }}%"></div></div>
                    </div>
                @endif
            @else
                <div class="flex flex-col items-center py-8 text-center">
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600"><x-ui.icon name="scale" class="size-7" /></span>
                    <p class="mt-3 font-semibold text-gray-900">Not enough weigh-ins yet</p>
                    <p class="mt-1 max-w-xs text-sm text-gray-500">Log your weight in the Fitnix app or at the front desk. Your trend appears here after two weigh-ins.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header flex-wrap gap-3"><h2 class="card-title">Attendance</h2><span class="text-xs text-gray-500">Workout consistency</span></div>
        <div class="card-body" wire:ignore x-data="{
                mode: 'weekly',
                data: @js($visits),
                draw() {
                    const rows = this.data[this.mode];
                    renderChart(this.$refs.c, { type: 'bar', data: { labels: rows.map(r => r[0]), datasets: [{ label: 'Visits', data: rows.map(r => r[1]), backgroundColor: rows.map((r, i) => i === rows.length - 1 ? chartColors.primary : chartColors.primaryHalf), borderRadius: 6 }] } });
                },
            }" x-init="draw()">
            <div class="mb-3 inline-flex rounded-full border border-gray-200 p-0.5 text-xs font-medium dark:border-ink-700" role="group" aria-label="View">
                @foreach (['weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
                    <button type="button" x-on:click="mode = '{{ $key }}'; draw()" :class="mode === '{{ $key }}' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:text-gray-900'" class="rounded-full px-3 py-1 transition">{{ $label }}</button>
                @endforeach
            </div>
            <div class="h-56"><canvas x-ref="c" aria-label="Visits over time"></canvas></div>
        </div>
    </div>
</div>
