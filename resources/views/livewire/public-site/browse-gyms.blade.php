<div class="card card-body sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Browse gyms</h1>
            <p class="mt-1 text-sm text-gray-500">See the gyms on Gymai in your city. Open a gym to view its photos, facilities, timings and prices.</p>
        </div>
        <a href="{{ route('member.join') }}" class="btn-primary btn-sm">Join a gym</a>
    </div>

    @if (! $city)
        <h2 class="mt-6 text-sm font-semibold">Which city?</h2>
        <input wire:model.live.debounce.300ms="citySearch" type="search" class="input mt-3" placeholder="Type your city" autofocus>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @forelse ($cities as $c)
                <button type="button" wire:key="city-{{ $c->id }}" wire:click="selectCity({{ $c->id }})"
                        class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-left transition hover:border-brand-600 dark:border-ink-700">
                    <span>
                        <span class="block font-medium">{{ $c->name }}</span>
                        @if ($c->state)<span class="block text-xs text-gray-500">{{ $c->state }}</span>@endif
                    </span>
                    <span class="text-xs text-gray-500">{{ $c->gyms_count }} {{ \Illuminate\Support\Str::plural('gym', $c->gyms_count) }}</span>
                </button>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 sm:col-span-2">
                    {{ trim($citySearch) !== '' ? 'No city matches "'.trim($citySearch).'".' : "Gymai isn't live in any city yet." }}
                </p>
            @endforelse
        </div>
    @else
        <div class="mt-6 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold"><x-ui.icon name="globe" class="mr-1 inline size-4" />Gyms in {{ $city->name }}</h2>
            <button type="button" wire:click="changeCity" class="text-sm font-medium text-gray-500 hover:text-brand-600">← Change city</button>
        </div>

        @if ($localities->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" wire:click="selectLocality" class="rounded-full border px-3 py-1 text-sm {{ $localityId ? 'border-gray-200 text-gray-500 dark:border-ink-700' : 'border-brand-600 text-brand-600' }}">All areas</button>
                @foreach ($localities as $l)
                    <button type="button" wire:key="loc-{{ $l->id }}" wire:click="selectLocality({{ $l->id }})"
                            class="rounded-full border px-3 py-1 text-sm {{ $localityId === $l->id ? 'border-brand-600 text-brand-600' : 'border-gray-200 text-gray-500 dark:border-ink-700' }}">{{ $l->name }} · {{ $l->gyms_count }}</button>
                @endforeach
            </div>
        @endif

        <input wire:model.live.debounce.300ms="search" type="search" class="input mt-3" placeholder="Search by gym name">

        <div class="mt-3 space-y-2">
            @forelse ($gyms as $g)
                @php $area = $g->locality && ! \Illuminate\Support\Str::contains((string) $g->address, $g->locality->name, true) ? $g->locality->name : null; @endphp
                <div wire:key="gym-{{ $g->id }}" class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 dark:border-ink-700">
                    @if ($g->logoUrl())
                        <img src="{{ $g->logoUrl() }}" alt="" class="size-10 shrink-0 rounded-lg bg-white object-contain p-0.5">
                    @else
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-600/15 text-sm font-bold text-brand-600">{{ $g->initials() }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ $g->name }}</p>
                        @if ($area || $g->address)<p class="truncate text-xs text-gray-500">{{ collect([$area, $g->address])->filter()->implode(' · ') }}</p>@endif
                    </div>
                    <a href="{{ $g->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="btn-ghost btn-sm shrink-0" title="Location"><x-ui.icon name="map-pin" class="size-4" /><span class="sr-only">Location</span></a>
                    <a href="{{ $g->publicUrl() }}" target="_blank" rel="noopener" class="btn-secondary btn-sm shrink-0"><x-ui.icon name="eye" class="size-4" /> View gym</a>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500">{{ trim($search) !== '' ? 'No gym matches "'.trim($search).'".' : 'No gyms here yet.' }}</p>
            @endforelse
        </div>
    @endif
</div>
