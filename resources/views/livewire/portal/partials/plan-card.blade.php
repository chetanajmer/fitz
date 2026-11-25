<a href="{{ route('portal.plans.show', $p->id) }}" wire:key="plan-{{ $p->id }}"
   class="card flex overflow-hidden transition hover:shadow-md {{ $highlight ? 'ring-2 ring-indigo-200' : '' }}">
    @if ($p->coverUrl())
        <img src="{{ $p->coverUrl() }}" alt="" class="w-28 shrink-0 object-cover">
    @else
        <div class="flex w-28 shrink-0 items-center justify-center bg-gradient-to-br from-indigo-50 to-amber-50 text-indigo-300"><x-ui.icon :name="$p->type->value === 'diet' ? 'meal' : 'dumbbell'" class="size-10" /></div>
    @endif
    <div class="min-w-0 flex-1 p-4">
        <p class="truncate font-semibold text-gray-900">{{ $p->title }}</p>
        @if ($p->summary)<p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $p->summary }}</p>@endif
        <p class="mt-2 text-xs text-gray-500">
            {{ $p->sections_count }} {{ $p->type->value === 'diet' ? 'meals' : 'exercises' }}
            @if ($p->daily_calories) · {{ number_format($p->daily_calories) }} kcal @endif
            @if ($p->level) · {{ \App\Models\FitnessPlan::LEVELS[$p->level] ?? $p->level }} @endif
        </p>
    </div>
</a>
