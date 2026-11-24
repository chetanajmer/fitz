{{-- AI Coach: progress-photo comparison (drag to wipe) and a progress report. Figures are demo data.
     The photo is one AI-generated side-by-side image (public/images/landing/ai-coach-before-after.jpg): each layer
     shows one half at 200% width, and the bottom (with the image's own BEFORE/AFTER text) is cropped off. --}}
@php
    $comparisonImage = asset('images/landing/ai-coach-before-after.jpg');
@endphp
<section id="ai-coach" data-lp-section class="relative overflow-hidden bg-forest py-16 text-chalk sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-3xl">
                <p class="lp-mono flex items-center gap-2 text-volt"><x-ui.icon name="sparkles" class="size-4" /> AI Coach</p>
                <h2 data-lp-slam class="lp-display mt-3 text-5xl sm:text-7xl">Proof they're <span class="text-volt">getting stronger.</span></h2>
            </div>
            <p class="max-w-sm font-medium text-chalk/75">AI Coach turns check-ins, weight logs and progress photos into a clear report for every member. Members opt in first, and you can read every report.</p>
        </div>

        <div class="mt-12 grid gap-8 lg:grid-cols-[22rem_1fr]">
            {{-- Before / after wipe --}}
            <div data-lp-compare class="lp-compare lp-reveal relative mx-auto aspect-[3/5] w-full max-w-md overflow-hidden border-2 border-chalk/30 bg-forest-soft select-none lg:max-w-none">
                <div class="absolute inset-0 overflow-hidden">
                    <img src="{{ $comparisonImage }}" alt="" width="736" height="736" loading="lazy" decoding="async" class="absolute top-0 left-0 h-auto w-[200%] max-w-none">
                    <span class="lp-mono absolute top-4 left-4 bg-chalk px-2 py-1 text-forest">Week 01 · 104 kg</span>
                </div>
                <div class="lp-compare-after absolute inset-0 overflow-hidden">
                    <img src="{{ $comparisonImage }}" alt="" width="736" height="736" loading="lazy" decoding="async" class="absolute top-0 -left-full h-auto w-[200%] max-w-none">
                    <span class="lp-mono absolute top-4 right-4 bg-volt px-2 py-1 text-forest">Week 36 · 78 kg</span>
                </div>
                <div class="lp-compare-handle pointer-events-none absolute inset-y-0 w-0.5 -translate-x-1/2 bg-clay">
                    <span class="absolute top-1/2 left-1/2 flex size-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center bg-clay text-forest"><x-ui.icon name="arrow-left" class="size-4" /><x-ui.icon name="arrow-right" class="size-4" /></span>
                </div>
                <span class="lp-mono pointer-events-none absolute right-3 bottom-3 bg-forest/80 px-2 py-1 text-[0.6rem] text-chalk/80">Illustrative · AI-generated image</span>
                <label class="sr-only" for="lp-compare-range">Compare week 1 and week 36 progress photos</label>
                <input id="lp-compare-range" type="range" min="0" max="100" value="50" data-lp-compare-input>
            </div>

            {{-- Progress report --}}
            <div class="lp-report lp-reveal flex flex-col bg-chalk p-6 text-forest sm:p-8" style="--d: 120ms">
                <div class="flex items-center justify-between gap-4 border-b-2 border-forest pb-4">
                    <p class="lp-display text-3xl">Progress report</p>
                    <span class="lp-mono bg-volt px-2 py-1">Week 36</span>
                </div>
                <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                    @foreach (['−26 kg' => 'Weight', '148' => 'Check-ins', '63' => 'Day streak'] as $value => $label)
                        <div class="border-2 border-forest p-3"><p class="lp-display text-3xl">{{ $value }}</p><p class="lp-mono mt-1">{{ $label }}</p></div>
                    @endforeach
                </div>
                <div class="mt-6 space-y-3 text-[15px] leading-relaxed font-medium">
                    <p class="lp-typeline" style="--i: 0"><strong>Summary.</strong> Arjun trained 4–5 times a week for nine months and went from 104 kg to 78 kg while building muscle.</p>
                    <p class="lp-typeline" style="--i: 1"><strong>Photos.</strong> Week 36 shows a much leaner waist, visible abs and broader shoulders than week 1.</p>
                    <p class="lp-typeline" style="--i: 2"><strong>Next.</strong> Switch to the lean-bulk diet plan and add a second upper-body day to keep the strength gains coming.</p>
                </div>
                <p class="lp-mono mt-auto flex items-center gap-2 pt-6 text-forest/60"><span class="lp-caret inline-block h-3.5 w-2 bg-clay"></span> Written by AI Coach · shared with the gym</p>
            </div>
        </div>
    </div>
</section>
