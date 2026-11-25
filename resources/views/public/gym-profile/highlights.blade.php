{{-- At-a-glance numbers, all taken from the gym's own profile. --}}
@if ($highlights->count() >= 2)
    <section aria-label="At a glance" class="border-y border-gym-border bg-gym-black">
        <dl class="mx-auto grid max-w-7xl grid-cols-2 divide-gym-border px-4 sm:px-6 lg:px-8 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$highlights->count()] ?? 'md:grid-cols-4' }} md:divide-x">
            @foreach ($highlights->take(4) as $i => $h)
                <div class="gp-reveal flex flex-col-reverse px-2 py-8 text-center md:px-6" style="--d: {{ $i * 90 }}ms">
                    <dt class="mt-1 text-sm text-gym-muted">{{ $h['label'] }}</dt>
                    <dd class="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                        @isset($h['display'])
                            <span data-count="{{ $h['value'] }}" data-count-label="{{ $h['display'] }}">{{ $h['display'] }}</span>
                        @else
                            <span data-count="{{ $h['value'] }}">{{ $h['value'] }}</span>
                        @endisset
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif
