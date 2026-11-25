{{-- One finished AI report: cards from its fields, or the Markdown of older reports. --}}
@props(['report', 'photoUrl'])
@php($r = $report->result)
@if ($report->type === \App\Models\AiReport::PHOTO_COMPARISON && $report->before_photo_id && $report->after_photo_id)
    <div class="mb-4 max-w-sm">
        <x-progress.before-after :before="$photoUrl($report->before_photo_id)" :after="$photoUrl($report->after_photo_id)" />
    </div>
@endif
@if ($r)
    <div class="space-y-4 text-sm">
        <div class="flex flex-wrap gap-2">
            @isset($r['change_level'])<x-ui.badge :color="['strong' => 'green', 'moderate' => 'green', 'slight' => 'blue'][$r['change_level']] ?? 'gray'">{{ \App\Models\AiReport::CHANGE_LEVELS[$r['change_level']] ?? $r['change_level'] }}</x-ui.badge>@endisset
            @isset($r['photo_quality'])<x-ui.badge :color="['good' => 'green', 'fair' => 'amber'][$r['photo_quality']] ?? 'red'">Photos: {{ $r['photo_quality'] }}</x-ui.badge>@endisset
            <x-ui.badge color="gray">Confidence: {{ $r['confidence'] }}</x-ui.badge>
        </div>
        <p>{{ $r['summary'] }}</p>
        @if (! empty($r['changes']))
            <div class="grid gap-2 sm:grid-cols-2">@foreach ($r['changes'] as $c)<div class="rounded-lg border border-gray-200 p-3"><p class="font-semibold">{{ $c['area'] }}</p><p class="text-gray-500">{{ $c['observation'] }}</p></div>@endforeach</div>
        @endif
        @if (! empty($r['numbers']))<div><p class="font-semibold">What the numbers say</p><p class="text-gray-500">{{ $r['numbers'] }}</p></div>@endif
        @foreach (['wins' => "What's working", 'photo_tips' => 'Tips for your next photo'] as $key => $title)
            @if (! empty($r[$key]))<div><p class="font-semibold">{{ $title }}</p><ul class="list-disc pl-5 text-gray-500">@foreach ($r[$key] as $line)<li>{{ $line }}</li>@endforeach</ul></div>@endif
        @endforeach
        @if (! empty($r['focus_areas']))
            <div><p class="mb-2 font-semibold">What to focus on next</p>
                <div class="grid gap-2 sm:grid-cols-2">@foreach ($r['focus_areas'] as $f)<div class="rounded-lg border border-gray-200 p-3"><p class="font-semibold">{{ $f['title'] }}</p><p class="text-gray-500">{{ $f['tip'] }}</p></div>@endforeach</div>
            </div>
        @endif
        <p class="font-medium text-brand-600">{{ $r['motivation'] }}</p>
    </div>
@else
    <div class="space-y-2 text-sm text-gray-700 [&_h2]:mt-3 [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:pl-5">
        {!! \Illuminate\Support\Str::markdown((string) $report->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
    </div>
@endif
