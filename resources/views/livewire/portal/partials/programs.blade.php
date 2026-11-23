{{--
    The member's assigned programs: program card, today's plan (with done / skip), and the next 7 days.
    $programs  ProgramService::overview()
    $types     which program types to show (['workout', 'diet'] or one of them)
    $detailed  list today's meals / exercises inline
--}}
@php
    $labels = ['workout' => ['dumbbell', 'My workout program', 'exercises', 'workout'], 'diet' => ['meal', 'My diet program', 'meals', 'diet']];
    $assigned = collect($types)->filter(fn ($t) => $programs[$t]);
    $statusIcon = ['done' => ['check', 'text-emerald-600'], 'skipped' => ['x', 'text-gray-400'], 'missed' => ['x', 'text-red-400']];
@endphp

@if ($assigned->isNotEmpty())
    <section class="space-y-4">
        @error('program') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

        <div class="grid gap-4 {{ count($types) > 1 ? 'md:grid-cols-2' : '' }}">
            @foreach ($types as $type)
                @php [$icon, $label, $itemsLabel, $noun] = $labels[$type]; $s = $programs[$type]; $t = $programs['today'][$type]; @endphp
                <div wire:key="prog-{{ $type }}" class="card card-body">
                    <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-gray-500 uppercase"><x-ui.icon :name="$icon" class="size-4" /> {{ $label }}</p>
                    @if ($s)
                        <h3 class="mt-2 font-semibold text-gray-900">{{ $s['program']['title'] }}</h3>
                        <p class="text-xs text-gray-500">
                            {{ $s['program']['goal_label'] }}@if ($s['program']['diet_preference_label']) · {{ $s['program']['diet_preference_label'] }}@endif
                            · {{ \Illuminate\Support\Carbon::parse($s['start_date'])->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($s['end_date'])->format('d M Y') }}
                        </p>
                        @if ($s['state'] === 'upcoming')
                            <p class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800">Starts on {{ \Illuminate\Support\Carbon::parse($s['start_date'])->format('D, d M') }}.</p>
                        @else
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-rose-500" style="width: {{ $s['progress_percent'] }}%"></div></div>
                            <p class="mt-1 flex flex-wrap justify-between gap-x-3 text-xs text-gray-500">
                                <span>Day {{ $s['current_day'] }} of {{ $s['total_days'] }} · week {{ $s['current_week'] }}</span>
                                @if ($s['adherence']['planned_days'])
                                    <span class="font-medium {{ $s['adherence']['percent'] >= 70 ? 'text-emerald-600' : 'text-amber-600' }}">Followed {{ $s['adherence']['done_days'] }} of {{ $s['adherence']['planned_days'] }} days ({{ round($s['adherence']['percent']) }}%)</span>
                                @endif
                            </p>
                        @endif
                        @if ($s['notes'])<p class="mt-2 text-sm text-gray-600">{{ $s['notes'] }}</p>@endif

                        @if ($t)
                            <div class="mt-4 rounded-xl {{ $t['is_rest'] ? 'bg-gray-50' : ($t['status'] === 'done' ? 'bg-emerald-50' : 'bg-rose-50') }} p-4">
                                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Today</p>
                                <p class="mt-1 flex items-center gap-2 font-semibold text-gray-900">
                                    <x-ui.icon :name="$t['is_rest'] ? 'moon' : $icon" class="size-5 shrink-0 {{ $t['is_rest'] ? 'text-gray-400' : 'text-rose-500' }}" /> {{ $t['title'] }}
                                </p>
                                @if ($t['notes'])<p class="mt-1 text-sm text-gray-600">{{ $t['notes'] }}</p>@endif
                                @if ($t['is_rest'])
                                    <p class="mt-1 text-sm text-gray-500">Rest day — light walking and stretching only.</p>
                                @elseif ($t['plan'])
                                    @if ($detailed && ! empty($t['plan']['sections']))
                                        <ol class="mt-3 space-y-2">
                                            @foreach ($t['plan']['sections'] as $i => $section)
                                                <li wire:key="today-{{ $type }}-{{ $section['id'] }}" class="flex gap-3 rounded-lg bg-white p-3 text-sm shadow-sm">
                                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-rose-100 text-xs font-semibold text-rose-700">{{ $i + 1 }}</span>
                                                    <span class="min-w-0">
                                                        <span class="block font-medium text-gray-900">{{ $section['title'] }}</span>
                                                        @if ($section['subtitle'])<span class="block text-xs text-gray-500">{{ $section['subtitle'] }}</span>@endif
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                    <a href="{{ route('portal.plans.show', $t['plan']['id']) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-rose-600 hover:underline">
                                        {{ $detailed ? 'Open full plan' : 'See today\'s '.$itemsLabel }} <x-ui.icon name="arrow-right" class="size-4" />
                                    </a>

                                    <div class="mt-4 border-t border-black/5 pt-3">
                                        @if ($t['status'] === 'done')
                                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-emerald-700">
                                                <x-ui.icon name="check" class="size-5" /> Today's {{ $noun }} done
                                                <button wire:click="undoProgramDay('{{ $type }}')" class="text-xs font-medium text-gray-500 hover:underline">Undo</button>
                                            </p>
                                        @elseif ($t['status'] === 'skipped')
                                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-gray-600">
                                                <x-ui.icon name="x" class="size-5" /> Skipped today
                                                <button wire:click="undoProgramDay('{{ $type }}')" class="text-xs font-medium text-gray-500 hover:underline">Undo</button>
                                            </p>
                                        @else
                                            <div class="flex gap-2">
                                                <button wire:click="logProgramDay('{{ $type }}', 'done')" wire:loading.attr="disabled" class="btn flex-1 bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500">
                                                    <x-ui.icon name="check" class="size-5" /> {{ $type === 'diet' ? 'I followed my diet' : 'Workout done' }}
                                                </button>
                                                <button wire:click="logProgramDay('{{ $type }}', 'skipped')" wire:loading.attr="disabled" class="btn-secondary">Skip</button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-500">Your trainer hasn't assigned a {{ $type }} program yet.</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card overflow-x-auto">
            <div class="card-header"><h2 class="card-title">My schedule — next 7 days</h2></div>
            <div class="grid min-w-[42rem] grid-cols-7 divide-x divide-gray-100">
                @foreach ($programs['week'] as $d)
                    <div wire:key="wk-{{ $d['date'] }}" class="p-3 text-xs {{ $d['is_today'] ? 'bg-rose-50/70' : '' }}">
                        <p class="font-semibold {{ $d['is_today'] ? 'text-rose-700' : 'text-gray-700' }}">{{ $d['is_today'] ? 'Today' : $d['weekday'] }} {{ \Illuminate\Support\Carbon::parse($d['date'])->format('d') }}</p>
                        @php $shown = false; @endphp
                        @foreach (['workout', 'diet'] as $kind)
                            @if (in_array($kind, $types, true) && $d[$kind])
                                @php $shown = true; $mark = $statusIcon[$d[$kind]['status']] ?? null; @endphp
                                <p class="mt-1 flex items-start gap-1 {{ $d[$kind]['is_rest'] ? 'text-gray-400' : ($kind === 'workout' ? 'text-gray-800' : 'text-gray-600') }}">
                                    <x-ui.icon :name="$d[$kind]['is_rest'] ? 'moon' : $labels[$kind][0]" class="mt-px size-3.5 shrink-0" />
                                    <span class="flex-1">{{ $d[$kind]['title'] }}</span>
                                    @if ($mark)<x-ui.icon :name="$mark[0]" class="mt-px size-3.5 shrink-0 {{ $mark[1] }}" />@endif
                                </p>
                            @endif
                        @endforeach
                        @if (! $shown)<p class="mt-1 text-gray-300">—</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
