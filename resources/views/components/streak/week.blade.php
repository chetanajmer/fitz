{{-- Mon→Sun dots, same states as the member app: attended | missed | closed | today | upcoming --}}
@props(['week', 'size' => 'md'])
@php
    $dot = $size === 'sm' ? 'size-7 text-xs' : 'size-9 text-sm';
@endphp
<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-1']) }}>
    @foreach ($week as $day)
        <div class="flex flex-col items-center gap-1" title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, d M') }} · {{ ucfirst($day['status']) }}">
            @switch($day['status'])
                @case('attended')
                    <span class="flex {{ $dot }} items-center justify-center rounded-full bg-rose-600 text-white"><x-ui.icon name="check" class="size-4" /></span>
                    @break
                @case('today')
                    <span class="flex {{ $dot }} items-center justify-center rounded-full border-2 border-dashed border-rose-500"></span>
                    @break
                @case('closed')
                    <span class="flex {{ $dot }} items-center justify-center rounded-full bg-gray-100 text-[10px] text-gray-400">off</span>
                    @break
                @case('missed')
                    <span class="flex {{ $dot }} items-center justify-center rounded-full border border-gray-300 bg-white"></span>
                    @break
                @default
                    <span class="flex {{ $dot }} items-center justify-center rounded-full border border-gray-200 bg-gray-50"></span>
            @endswitch
            <span class="text-xs {{ $day['status'] === 'today' ? 'font-bold text-gray-900' : 'text-gray-500' }}">{{ $day['label'] }}</span>
        </div>
    @endforeach
</div>
