{{-- Renders a diet chart / workout plan: cover, meta, and sections (photo + text). Text is escaped; line breaks kept. --}}
@props(['plan'])
@php($isDiet = $plan->type === \App\Enums\FitnessPlanType::Diet)
<article {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if ($plan->coverUrl())
        <img src="{{ $plan->coverUrl() }}" alt="" class="aspect-[21/9] w-full object-cover">
    @endif
    <div class="card-body sm:p-8">
        <div class="flex flex-wrap gap-1.5">
            @if ($plan->isCustom())<x-ui.badge color="red">Customised for {{ $plan->member?->name }}</x-ui.badge>@endif
            @if ($plan->category)<x-ui.badge color="indigo">{{ $plan->category->name }}</x-ui.badge>@endif
            @if ($plan->level)<x-ui.badge>{{ \App\Models\FitnessPlan::LEVELS[$plan->level] ?? $plan->level }}</x-ui.badge>@endif
        </div>
        <h1 class="mt-3 text-2xl font-bold text-gray-900 sm:text-3xl">{{ $plan->title }}</h1>
        @if ($plan->summary)<p class="mt-2 whitespace-pre-line text-gray-600">{{ $plan->summary }}</p>@endif
        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
            <span class="inline-flex items-center gap-1.5"><x-ui.icon :name="$isDiet ? 'meal' : 'dumbbell'" class="size-4 text-gray-400" /> {{ $plan->sections->count() }} {{ $isDiet ? 'meals' : 'exercises' }}</span>
            @if ($plan->daily_calories)<span class="inline-flex items-center gap-1.5"><x-ui.icon name="fire" class="size-4 text-orange-500" /> {{ number_format($plan->daily_calories) }} kcal / day</span>@endif
            @if ($plan->duration_weeks)<span class="inline-flex items-center gap-1.5"><x-ui.icon name="calendar" class="size-4 text-gray-400" /> {{ $plan->duration_weeks }} weeks</span>@endif
        </div>

        <ol class="mt-8 space-y-5">
            @foreach ($plan->sections as $i => $section)
                <li class="flex flex-col gap-4 rounded-xl border border-gray-100 bg-gray-50/50 p-4 sm:flex-row">
                    @if ($section->imageUrl())
                        <img src="{{ $section->imageUrl() }}" alt="{{ $section->title }}" loading="lazy" class="aspect-[4/3] w-full shrink-0 rounded-lg object-cover sm:w-48">
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline gap-2">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">{{ $i + 1 }}</span>
                            <h3 class="font-semibold text-gray-900">{{ $section->title }}</h3>
                        </div>
                        @if ($section->subtitle)<p class="mt-1 text-sm font-medium text-indigo-600">{{ $section->subtitle }}</p>@endif
                        @if ($section->body)<p class="mt-2 text-sm leading-relaxed whitespace-pre-line text-gray-700">{{ $section->body }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</article>
