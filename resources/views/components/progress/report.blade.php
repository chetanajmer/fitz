{{-- One finished AI report: a poster panel and lists from its fields, or the Markdown of older reports. $shareName: set only for the member's own page. --}}
@props(['report', 'photoUrl', 'highlight' => null, 'shareName' => null])
@php
    $r = $report->result;
    $hasPhotos = $report->type === \App\Models\AiReport::PHOTO_COMPARISON && $report->before_photo_id && $report->after_photo_id;
    $declined = ($r['direction'] ?? null) === 'declined';
    $step = $declined ? null : ['none' => 0, 'slight' => 1, 'moderate' => 2, 'strong' => 3][$r['change_level'] ?? ''] ?? null;
    $marks = ['better' => ['check', 'text-emerald-600', 'Better'], 'worse' => ['arrow-down', 'text-amber-600', 'Worse'], 'neutral' => ['arrow-right', 'text-gray-400', 'Different']];
@endphp
<div class="space-y-6 text-sm">
    @if ($highlight || $hasPhotos)
        {{-- Always black, in both themes: the poster the member can share. --}}
        <div @class(['overflow-hidden rounded-xl bg-gym-black text-white ring-1 ring-gym-border', 'grid md:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]' => $hasPhotos])>
            @if ($hasPhotos)
                <x-progress.before-after :before="$photoUrl($report->before_photo_id)" :after="$photoUrl($report->after_photo_id)" reveal class="rounded-none border-0" />
            @endif
            <div class="flex min-w-0 flex-col justify-center gap-5 p-6 sm:p-8">
                <div>
                    <p class="font-display text-5xl leading-none text-balance uppercase sm:text-6xl">{{ $highlight['headline'] ?? \App\Models\AiReport::CHANGE_LEVELS[$r['change_level'] ?? ''] ?? '' }}</p>
                    @if ($highlight['period'] ?? null)<p class="mt-3 text-xl font-medium text-gym-orange-light">{{ $highlight['period'] }}</p>@endif
                </div>
                @if ($step)
                    <div class="flex max-w-72 gap-1.5" aria-hidden="true">
                        @foreach ([1, 2, 3] as $bar)<span @class(['h-2.5 flex-1 -skew-x-12', 'bg-gym-orange' => $bar <= $step, 'bg-gym-border' => $bar > $step])></span>@endforeach
                    </div>
                @endif
                @if ($highlight['stats'] ?? [])
                    <dl class="flex flex-wrap gap-x-8 gap-y-4">
                        @foreach ($highlight['stats'] as $stat)
                            <div>
                                <dd class="font-display text-3xl leading-tight text-gym-orange">{{ $stat['value'] }}</dd>
                                <dt class="text-gym-muted">{{ $stat['label'] }}@if ($stat['detail']) <span class="tabular-nums">{{ $stat['detail'] }}</span>@endif</dt>
                            </div>
                        @endforeach
                    </dl>
                @endif
                @if ($highlight && $highlight['shareable'] && $shareName)
                    <x-progress.share :report="$report" :highlight="$highlight" :photo-url="$photoUrl" :name="$shareName" :step="$step" />
                @endif
            </div>
        </div>
    @endif

    @if ($r)
        <div class="min-w-0 space-y-6">
            <p class="max-w-prose text-base leading-relaxed text-gray-700">{{ $r['summary'] }}</p>

            @if (! ($highlight['stats'] ?? []) && ! empty($r['numbers']))
                <p class="max-w-prose text-gray-500">{{ $r['numbers'] }}</p>
            @endif

            @if (! empty($r['changes']))
                <div>
                    <h3 class="mb-2 font-semibold text-gray-900">What changed</h3>
                    <ul class="space-y-3">
                        @foreach ($r['changes'] as $c)
                            @php([$icon, $color, $label] = $marks[$c['direction'] ?? 'neutral'] ?? $marks['neutral'])
                            <li class="flex gap-3"><x-ui.icon :name="$icon" class="mt-0.5 size-4 shrink-0 {{ $color }}" /><p class="max-w-prose"><span class="block font-medium text-gray-900">{{ $c['area'] }} <span class="text-xs font-normal {{ $color }}">{{ isset($c['direction']) ? $label : '' }}</span></span><span class="text-gray-500">{{ $c['observation'] }}</span></p></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($r['wins']))
                <div>
                    <h3 class="mb-2 font-semibold text-gray-900">What's working</h3>
                    <ul class="space-y-3">
                        @foreach ($r['wins'] as $win)<li class="flex gap-3 text-gray-700"><x-ui.icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /><span class="max-w-prose">{{ $win }}</span></li>@endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($r['focus_areas']))
                <div>
                    <h3 class="mb-2 font-semibold text-gray-900">Where to work next</h3>
                    <ul class="max-w-2xl divide-y divide-gray-100">
                        @foreach ($r['focus_areas'] as $f)
                            @php($isFirst = $loop->first && $loop->count > 1)
                            <li @class(['border-l-4 py-3 pl-4', 'border-l-brand-600' => $isFirst, 'border-l-gray-300' => ! $isFirst])>
                                <p class="flex flex-wrap items-center gap-2 font-medium text-gray-900">{{ $f['title'] }}@if ($isFirst)<x-ui.badge color="indigo">Start here</x-ui.badge>@endif</p>
                                @isset($f['why'])<p class="text-gray-500">{{ $f['why'] }}</p>@endisset
                                <p class="mt-1 flex gap-2 text-gray-700"><x-ui.icon name="arrow-right" class="mt-0.5 size-4 shrink-0 text-brand-600" /><span>{{ $f['tip'] }}</span></p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($r['missing']))
                <div>
                    <h3 class="mb-2 font-semibold text-gray-900">What's missing</h3>
                    <ul class="space-y-2">
                        @foreach ($r['missing'] as $gap)<li class="flex gap-3 text-gray-700"><x-ui.icon name="plus" class="mt-0.5 size-4 shrink-0 text-gray-400" /><span class="max-w-prose">{{ $gap }}</span></li>@endforeach
                    </ul>
                </div>
            @endif

            <p @class(['max-w-prose rounded-lg px-4 py-3', 'bg-gray-100 text-gray-700' => $declined, 'bg-emerald-50 text-emerald-800' => ! $declined])>{{ $r['motivation'] }}</p>

            <div class="space-y-2 text-gray-500">
                @if (! empty($r['photo_tips']))
                    <details wire:ignore.self>
                        <summary class="cursor-pointer font-medium text-gray-700">Tips for your next photo</summary>
                        <ul class="mt-2 max-w-prose list-disc space-y-1 pl-5">@foreach ($r['photo_tips'] as $tip)<li>{{ $tip }}</li>@endforeach</ul>
                    </details>
                @endif
                <p class="text-xs">
                    @isset($r['photo_quality']){{ ['good' => 'The photos compare well.', 'fair' => 'The photos compare fairly well.'][$r['photo_quality']] ?? 'The photos are hard to compare.' }}@endisset
                    {{ ['high' => 'The AI is confident in this report.', 'medium' => 'The AI is fairly confident in this report.'][$r['confidence']] ?? 'There was little to go on, so treat this as a rough guide.' }}
                </p>
            </div>
        </div>
    @else
        <div class="min-w-0 max-w-prose space-y-2 text-gray-700 [&_h2]:mt-3 [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:pl-5">
            {!! \Illuminate\Support\Str::markdown((string) $report->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </div>
    @endif
</div>
