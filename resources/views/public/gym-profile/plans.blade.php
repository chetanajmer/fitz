{{-- Membership plans: a swipe row on phones, a grid from md up. The best value (lowest cost per month) is marked. --}}
@if ($plans->isNotEmpty())
    <section id="plans" class="gp-section relative overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute top-1/3 left-1/2 -z-10 size-[40rem] -translate-x-1/2 rounded-full bg-gym-orange/10 blur-[140px]"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="gp-reveal gp-eyebrow">Membership plans</p>
                <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Pick your <span class="text-gym-orange">plan.</span></h2>
                <p class="gp-reveal mt-5 text-gym-muted" style="--d: 160ms">Simple pricing, paid at the gym. Join online and choose your plan at the front desk.</p>
            </div>

            <div class="gp-snap -mx-4 mt-12 flex gap-5 overflow-x-auto px-4 pt-4 pb-6 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-3">
                @foreach ($planRows as $i => $row)
                    @php
                        $plan = $row['plan'];
                        $isBest = $plan->id === $bestValueId;
                    @endphp
                    <article @class([
                            'gp-reveal relative flex w-[82%] shrink-0 flex-col rounded-[1.5rem] border p-7 transition duration-300 hover:-translate-y-1 sm:w-[60%] md:w-auto',
                            'border-gym-orange bg-gradient-to-b from-gym-orange/15 to-gym-card shadow-[0_30px_60px_-30px_rgb(255_107_0/0.7)]' => $isBest,
                            'border-gym-border bg-gym-card hover:border-gym-orange/50' => ! $isBest,
                        ]) style="--d: {{ ($i % 3) * 90 }}ms">
                        @if ($isBest)
                            <span class="absolute -top-3 left-7 inline-flex items-center gap-1 rounded-full bg-gym-orange px-3 py-1 text-xs font-bold text-white"><x-ui.icon name="star" class="size-3.5" /> Best value</span>
                        @endif
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-xl font-semibold text-white">{{ $plan->name }}</h3>
                                <p class="mt-1 text-sm text-gym-muted">{{ $plan->durationLabel() }}</p>
                            </div>
                            <span class="flex size-11 items-center justify-center rounded-xl bg-white/5 text-gym-orange"><x-ui.icon name="dumbbell" class="size-5" /></span>
                        </div>
                        <p class="mt-7 text-5xl font-bold tracking-tight text-white">{{ money($plan->price, false) }}</p>
                        @if ($row['months'] && $row['months'] > 1.05)
                            <p class="mt-2 text-sm text-gym-orange-light">≈ {{ money(round($row['perMonth']), false) }} / month</p>
                        @else
                            <p class="mt-2 text-sm text-gym-muted">Pay at the front desk</p>
                        @endif
                        @if ($plan->description)<p class="mt-5 text-sm text-gym-light/75">{{ $plan->description }}</p>@endif
                        @if ($plan->features)
                            <ul class="mt-6 space-y-3 border-t border-white/10 pt-6 text-sm text-gym-light">
                                @foreach ($plan->features as $feature)
                                    <li class="flex gap-3"><span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-gym-orange/15 text-gym-orange"><x-ui.icon name="check" class="size-3.5" /></span>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-auto grid gap-2 pt-8">
                            <a href="{{ $joinUrl }}" class="gp-btn {{ $isBest ? 'gp-btn-solid' : 'gp-btn-ghost' }} w-full">Join now</a>
                            <a href="#enquire" class="py-1 text-center text-sm font-medium text-gym-muted transition hover:text-gym-orange">Ask about this plan</a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($plans->count() > 1)
                <p class="mt-2 text-center text-xs text-gym-muted md:hidden">Swipe to see all plans →</p>
            @endif
        </div>
    </section>
@endif
