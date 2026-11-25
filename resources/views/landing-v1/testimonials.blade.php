@php
    /**
     * Real gym-owner testimonials only. The section stays hidden until at least one is added, e.g.
     * ['quote' => '…', 'name' => 'Owner name', 'role' => 'Gym name · City', 'rating' => 5].
     *
     * @var list<array{quote:string, name:string, role:string, rating:int}> $testimonials
     */
    $testimonials = [];
@endphp
@if (count($testimonials))
<section id="testimonials" data-lp-section class="relative overflow-hidden border-y border-ink-700 bg-ink-900 py-14 sm:py-20">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">Testimonials</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Loved by gym owners</h2>
        </div>

        <div data-lp-slider class="lp-reveal relative mt-10" style="--d: 160ms" aria-roledescription="carousel" aria-label="Testimonials">
            <div class="overflow-hidden rounded-3xl">
                <div data-lp-slider-track class="flex transition-transform duration-700 ease-[cubic-bezier(0.2,0.7,0.2,1)]">
                    @foreach ($testimonials as $i => $t)
                        <figure class="w-full shrink-0 px-1" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ count($testimonials) }}">
                            <div class="rounded-3xl border border-ink-700 bg-ink-800/70 p-8 sm:p-12">
                                <div class="flex gap-1 text-amber-400" aria-label="{{ $t['rating'] }} out of 5 stars">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <x-ui.icon name="star" class="size-5 {{ $s <= $t['rating'] ? 'fill-current' : 'text-zinc-700' }}" />
                                    @endfor
                                </div>
                                <blockquote class="mt-6 text-xl leading-relaxed font-medium sm:text-2xl">“{{ $t['quote'] }}”</blockquote>
                                <figcaption class="mt-8 flex items-center gap-4">
                                    <span class="flex size-12 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-rose-800 font-semibold">{{ \Illuminate\Support\Str::of($t['name'])->explode(' ')->map(fn ($p) => $p[0])->implode('') }}</span>
                                    <span><span class="block font-semibold">{{ $t['name'] }}</span><span class="text-sm text-zinc-500">{{ $t['role'] }}</span></span>
                                </figcaption>
                            </div>
                        </figure>
                    @endforeach
                </div>
            </div>
            <div class="mt-6 flex items-center justify-center gap-4">
                <button type="button" data-lp-slider-prev class="flex size-10 items-center justify-center rounded-full border border-ink-700 text-zinc-300 transition hover:border-brand-600 hover:text-white" aria-label="Previous testimonial"><x-ui.icon name="arrow-right" class="size-4 rotate-180" /></button>
                <div class="flex gap-2">
                    @foreach ($testimonials as $i => $t)
                        <button type="button" data-lp-slider-dot="{{ $i }}" class="h-2 w-2 rounded-full bg-zinc-700 transition-all [&.is-active]:w-6 [&.is-active]:bg-brand-600" aria-label="Show testimonial {{ $i + 1 }}"></button>
                    @endforeach
                </div>
                <button type="button" data-lp-slider-next class="flex size-10 items-center justify-center rounded-full border border-ink-700 text-zinc-300 transition hover:border-brand-600 hover:text-white" aria-label="Next testimonial"><x-ui.icon name="arrow-right" class="size-4" /></button>
            </div>
        </div>
    </div>
</section>
@endif
