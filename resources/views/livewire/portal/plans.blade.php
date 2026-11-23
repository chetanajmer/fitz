<div class="space-y-8">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">{{ $planType->pluralLabel() }}</h1>
        <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5">
            <a href="{{ route('portal.plans', 'diet') }}" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $planType->value === 'diet' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Diet</a>
            <a href="{{ route('portal.plans', 'workout') }}" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $planType->value === 'workout' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Workouts</a>
        </div>
    </div>


    @if ($custom->isNotEmpty())
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-rose-600 uppercase"><x-ui.icon name="star" class="size-4" /> Made for you</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($custom as $p)
                    @include('livewire.portal.partials.plan-card', ['p' => $p, 'highlight' => true])
                @endforeach
            </div>
        </section>
    @endif

    @foreach ($categories as $category)
        <section wire:key="cat-{{ $category->id }}">
            <div class="mb-3 flex items-center gap-3">
                @if ($category->imageUrl())<img src="{{ $category->imageUrl() }}" alt="" class="size-10 rounded-lg object-cover">@endif
                <div>
                    <h2 class="font-semibold text-gray-900">{{ $category->name }}</h2>
                    @if ($category->description)<p class="text-sm text-gray-500">{{ $category->description }}</p>@endif
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($category->plans as $p)
                    @include('livewire.portal.partials.plan-card', ['p' => $p, 'highlight' => false])
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($uncategorized->isNotEmpty())
        <section>
            @if ($categories->isNotEmpty())<h2 class="mb-3 font-semibold text-gray-900">More plans</h2>@endif
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($uncategorized as $p)
                    @include('livewire.portal.partials.plan-card', ['p' => $p, 'highlight' => false])
                @endforeach
            </div>
        </section>
    @endif

    @if ($custom->isEmpty() && $categories->isEmpty() && $uncategorized->isEmpty())
        <div class="card"><x-ui.empty :message="'Your gym hasn\'t published any '.strtolower($planType->pluralLabel()).' yet.'" icon="tag" /></div>
    @endif
</div>
