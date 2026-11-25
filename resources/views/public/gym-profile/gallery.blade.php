{{-- The gym's photos as a bento grid, opening in a lightbox (arrows, Esc, swipe). --}}
@if ($photos->isNotEmpty())
    @php
        $gallery = $photos->map(fn ($p) => ['url' => $p->url(), 'caption' => $p->caption ?: $gym->name])->values();
        $count = $gallery->count();
        /*
         * Tile sizes that close every row for any number of photos: the first photo is a 2×2 feature, the next
         * four fill the 2×2 block beside it (4 columns from md), and the last photo stretches to finish its row.
         * Phones have 2 columns, so an odd leftover photo goes full width there.
         */
        $tileClass = function (int $i) use ($count): string {
            if ($i === 0) {
                return 'col-span-2 row-span-2';
            }
            $isLast = $i === $count - 1;
            $mobile = $isLast && ($count - 1) % 2 === 1 ? 'col-span-2' : '';
            $desktop = match (true) {
                $count === 2 => 'md:col-span-2 md:row-span-2',
                $count === 3 => 'md:col-span-2',
                $count === 4 && $i === 1 => 'md:col-span-2',
                $count >= 5 && $isLast && $i >= 5 && ($count - 5) % 4 !== 0 => [1 => 'md:col-span-4', 2 => 'md:col-span-3', 3 => 'md:col-span-2'][($count - 5) % 4],
                default => $mobile ? 'md:col-span-1' : '',
            };

            return trim($mobile.' '.$desktop);
        };
    @endphp
    <section id="gallery" class="gp-section gp-surface"
             x-data="{
                 photos: @js($gallery),
                 index: null,
                 startX: 0,
                 show(i) { this.index = i; document.documentElement.style.overflow = 'hidden'; },
                 close() { this.index = null; document.documentElement.style.overflow = ''; },
                 step(d) { this.index = (this.index + d + this.photos.length) % this.photos.length; },
             }"
             x-on:keydown.escape.window="index !== null && close()"
             x-on:keydown.arrow-right.window="index !== null && step(1)"
             x-on:keydown.arrow-left.window="index !== null && step(-1)">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="gp-reveal gp-eyebrow">Gallery</p>
                    <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Inside <span class="text-gym-orange">the gym.</span></h2>
                </div>
                <p class="gp-reveal text-gym-muted" style="--d: 160ms">{{ $photos->count() }} {{ \Illuminate\Support\Str::plural('photo', $photos->count()) }} · tap to enlarge</p>
            </div>

            <div class="mt-12 grid auto-rows-[11rem] grid-cols-2 gap-3 sm:auto-rows-[14rem] md:grid-cols-4">
                @foreach ($gallery as $i => $photo)
                    <button type="button" x-on:click="show({{ $i }})"
                            @class([
                                'gp-img-reveal group relative overflow-hidden rounded-3xl bg-gym-card focus-visible:outline-offset-4',
                                $tileClass($i),
                            ]) style="--d: {{ ($i % 4) * 90 }}ms" aria-label="Open photo {{ $i + 1 }}: {{ $photo['caption'] }}">
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['caption'] }}" loading="lazy" decoding="async" class="size-full object-cover group-hover:scale-105">
                        <span class="absolute inset-0 flex items-end bg-gradient-to-t from-black/70 via-transparent to-transparent p-4 opacity-0 transition duration-300 group-hover:opacity-100">
                            <span class="text-left text-sm font-medium text-white">{{ $photo['caption'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Lightbox --}}
        <div x-show="index !== null" x-cloak x-transition.opacity.duration.250ms role="dialog" aria-modal="true" aria-label="Photo viewer"
             class="fixed inset-0 z-[80] flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm"
             x-on:click.self="close()" x-on:touchstart.passive="startX = $event.touches[0].clientX"
             x-on:touchend.passive="const dx = $event.changedTouches[0].clientX - startX; if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1)">
            <template x-if="index !== null">
                <figure class="max-h-full max-w-5xl">
                    <img :src="photos[index].url" :alt="photos[index].caption" class="max-h-[80vh] w-auto rounded-2xl object-contain">
                    <figcaption class="mt-3 text-center text-sm text-gym-light"><span x-text="photos[index].caption"></span> · <span x-text="(index + 1) + ' / ' + photos.length"></span></figcaption>
                </figure>
            </template>
            <button type="button" x-on:click="close()" class="absolute top-4 right-4 flex size-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-gym-orange" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button>
            <template x-if="photos.length > 1">
                <div>
                    <button type="button" x-on:click="step(-1)" class="absolute top-1/2 left-3 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-gym-orange" aria-label="Previous photo"><x-ui.icon name="arrow-left" class="size-5" /></button>
                    <button type="button" x-on:click="step(1)" class="absolute top-1/2 right-3 flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-gym-orange" aria-label="Next photo"><x-ui.icon name="arrow-right" class="size-5" /></button>
                </div>
            </template>
        </div>
    </section>
@endif
