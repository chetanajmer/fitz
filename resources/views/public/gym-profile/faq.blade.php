{{-- Questions people ask a gym, answered from this gym's profile. --}}
@php
    $openDays = $hours->reject(fn ($h) => $h['closed']);
    $closedDays = $hours->filter(fn ($h) => $h['closed'])->pluck('day');
    $faqs = array_filter([
        $fullAddress || $location ? ['Where is '.$gym->name.'?', $gym->name.' is at '.($fullAddress ?: $location).'. Use "Get directions" above to open it in Google Maps.'] : null,
        $openDays->isNotEmpty() ? ['What are the gym timings?', $openDays->map(fn ($h) => $h['short'].' '.$h['text'])->implode(' · ').($closedDays->isNotEmpty() ? '. Closed on '.$closedDays->implode(', ').'.' : '.')] : null,
        ['How much does a membership cost?', $plans->isNotEmpty()
            ? 'Plans start at '.money($fromPrice, false).': '.$plans->map(fn ($p) => $p->name.' ('.$p->durationLabel().') '.money($p->price, false))->implode(', ').'. You pay at the gym.'
            : 'Please call or send an enquiry for current membership prices.'],
        ['How do I join?', 'Tap "Join now" to register online through Fitnix — choose your city, pick '.$gym->name.' and verify your mobile number. The gym approves your registration and you choose a plan at the front desk. You can also send an enquiry and the team will call you.'],
        ['Will I get a member app?', 'Yes. Once the gym approves you, sign in to the Fitnix app with your mobile number to check in with your QR code and see your workout plans, diet plans, attendance and progress.'],
        $facilities->isNotEmpty() ? ['What facilities are available?', $facilities->pluck('name')->implode(', ').'.'] : null,
    ]);
@endphp
<section id="faq" class="gp-section">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.75fr_1.25fr] lg:px-8">
        <div class="lg:sticky lg:top-28 lg:self-start">
            <p class="gp-reveal gp-eyebrow">FAQ</p>
            <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Good <span class="text-gym-orange">questions.</span></h2>
            <p class="gp-reveal mt-5 max-w-sm text-gym-muted" style="--d: 160ms">Can't find your answer? Ask the gym directly.</p>
            <a href="#enquire" class="gp-reveal gp-btn gp-btn-ghost mt-6" style="--d: 240ms">Ask a question</a>
        </div>

        <div class="space-y-3">
            @foreach (array_values($faqs) as $i => [$question, $answer])
                <details name="gp-faq" class="gp-reveal group rounded-2xl border border-gym-border bg-gym-card transition-colors open:border-gym-orange/50" style="--d: {{ $i * 60 }}ms" @if ($i === 0) open @endif>
                    <summary class="flex cursor-pointer items-center gap-4 px-5 py-5 sm:px-6">
                        <span class="w-7 shrink-0 font-mono text-xs text-gym-orange">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="flex-1 text-base font-semibold text-white sm:text-lg">{{ $question }}</span>
                        <span class="gp-faq-icon flex size-9 shrink-0 items-center justify-center rounded-full border border-gym-border text-lg text-gym-light">+</span>
                    </summary>
                    <p class="gp-faq-body px-5 pb-6 pl-16 leading-relaxed text-gym-light/80 sm:px-6 sm:pl-17">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
