{{-- One finished AI report: cards from its fields, or the Markdown of older reports. $readings: before/after values near the two photos. --}}
@props(['report', 'photoUrl', 'readings' => []])
@php
    $r = $report->result;
    $hasPhotos = $report->type === \App\Models\AiReport::PHOTO_COMPARISON && $report->before_photo_id && $report->after_photo_id;
    $step = ['none' => 0, 'slight' => 1, 'moderate' => 2, 'strong' => 3][$r['change_level'] ?? ''] ?? null;
    $signed = fn (float $value) => ($value > 0 ? '+' : ($value < 0 ? '−' : '')).abs($value);
@endphp
<div @class(['grid gap-6 text-sm', 'md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)]' => $hasPhotos])>
    @if ($hasPhotos)
        <div class="max-w-60">
            <x-progress.before-after :before="$photoUrl($report->before_photo_id)" :after="$photoUrl($report->after_photo_id)" />
            <p class="hint text-center">Drag to compare</p>
        </div>
    @endif

    @if ($r)
        <div class="min-w-0 space-y-6">
            <div class="space-y-3">
                @isset($r['change_level'])
                    <p class="text-xl font-semibold text-gray-900">{{ \App\Models\AiReport::CHANGE_LEVELS[$r['change_level']] ?? $r['change_level'] }}</p>
                    @if ($step !== null)
                        <div class="flex max-w-60 gap-1.5" aria-hidden="true">
                            @foreach ([1, 2, 3] as $bar)<span @class(['h-2 flex-1 rounded-full', 'bg-emerald-600' => $bar <= $step, 'bg-gray-200' => $bar > $step])></span>@endforeach
                        </div>
                    @endif
                @endisset
                <p class="max-w-prose text-base leading-relaxed text-gray-700">{{ $r['summary'] }}</p>
            </div>

            @if ($readings)
                <div>
                    <h3 class="mb-1 font-semibold text-gray-900">Your numbers</h3>
                    <dl class="max-w-md divide-y divide-gray-100">
                        @foreach ($readings as $reading)
                            <div class="grid grid-cols-[1fr_auto_5rem] items-center gap-3 py-2">
                                <dt class="text-gray-500">{{ $reading['label'] }}</dt>
                                <dd class="flex items-center gap-2 tabular-nums text-gray-700">{{ $reading['before'] }} <x-ui.icon name="arrow-right" class="size-3.5 text-gray-400" /> {{ $reading['after'] }}</dd>
                                <dd class="text-right font-semibold tabular-nums text-gray-900">{{ $reading['change'] == 0 ? 'Same' : $signed($reading['change']).' '.$reading['unit'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @elseif (! empty($r['numbers']))
                <p class="max-w-prose text-gray-500">{{ $r['numbers'] }}</p>
            @endif

            @if (! empty($r['changes']))
                <div>
                    <h3 class="mb-2 font-semibold text-gray-900">What changed</h3>
                    <ul class="space-y-3">
                        @foreach ($r['changes'] as $c)
                            <li class="flex gap-3"><x-ui.icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /><p class="max-w-prose"><span class="block font-medium text-gray-900">{{ $c['area'] }}</span><span class="text-gray-500">{{ $c['observation'] }}</span></p></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($r['wins']) || ! empty($r['focus_areas']))
                <div class="grid gap-6 md:grid-cols-2">
                    @if (! empty($r['wins']))
                        <div>
                            <h3 class="mb-2 font-semibold text-gray-900">What's working</h3>
                            <ul class="space-y-3">
                                @foreach ($r['wins'] as $win)<li class="flex gap-3 text-gray-700"><x-ui.icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /><span>{{ $win }}</span></li>@endforeach
                            </ul>
                        </div>
                    @endif
                    @if (! empty($r['focus_areas']))
                        <div>
                            <h3 class="mb-2 font-semibold text-gray-900">What to do next</h3>
                            <ul class="space-y-3">
                                @foreach ($r['focus_areas'] as $f)<li class="flex gap-3"><x-ui.icon name="arrow-right" class="mt-0.5 size-4 shrink-0 text-brand-600" /><p><span class="block font-medium text-gray-900">{{ $f['title'] }}</span><span class="text-gray-500">{{ $f['tip'] }}</span></p></li>@endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            <p class="max-w-prose rounded-lg bg-emerald-50 px-4 py-3 text-emerald-800">{{ $r['motivation'] }}</p>

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
