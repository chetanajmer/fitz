{{-- Opening hours (today highlighted) and how to reach the gym. --}}
<section id="visit" class="gp-section gp-surface relative">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <p class="gp-reveal gp-eyebrow">Visit us</p>
            <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Timings & <span class="text-gym-orange">location.</span></h2>
        </div>

        <div class="mt-12 grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="gp-reveal gp-card p-6 hover:translate-y-0 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2 text-lg font-semibold text-white"><x-ui.icon name="clock" class="size-5 text-gym-orange" /> Opening hours</h3>
                    @if ($status)
                        <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-400/15 text-emerald-400' => $status['live'], 'bg-gym-orange/15 text-gym-orange' => ! $status['live']])>{{ $status['label'] }}</span>
                    @endif
                </div>
                @if ($hours->isNotEmpty())
                    <dl class="mt-6 divide-y divide-white/5">
                        @foreach ($hours as $h)
                            <div @class(['-mx-3 flex items-center justify-between gap-4 rounded-xl px-3 py-3 text-sm', 'bg-gym-orange/10 ring-1 ring-gym-orange/40' => $h['today']])>
                                <dt @class(['flex items-center gap-2', 'font-semibold text-white' => $h['today'], 'text-gym-muted' => ! $h['today']])>
                                    {{ $h['day'] }} @if ($h['today'])<span class="rounded-full bg-gym-orange px-2 py-0.5 text-[10px] font-bold text-white uppercase">Today</span>@endif
                                </dt>
                                <dd @class(['font-medium', 'text-gym-muted/70' => $h['closed'], 'text-white' => ! $h['closed']])>{{ $h['text'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-6 text-sm text-gym-muted">Call the gym for today's timings.</p>
                @endif
            </div>

            <div class="grid gap-5">
                <div class="gp-reveal gp-card relative overflow-hidden p-6 sm:p-8" style="--d: 100ms">
                    <div aria-hidden="true" class="gp-grid-bg pointer-events-none absolute inset-0 opacity-60 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
                    <div class="relative">
                        <span class="flex size-12 items-center justify-center rounded-2xl bg-gym-orange text-white shadow-[0_0_30px_-6px_rgb(255_107_0/0.8)]"><x-ui.icon name="map-pin" class="size-6" /></span>
                        <h3 class="mt-5 text-lg font-semibold text-white">Find us</h3>
                        <p class="mt-1 text-gym-light/80">{{ $fullAddress ?: $gym->name }}</p>
                        <a href="{{ $gym->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="gp-btn gp-btn-solid mt-6 w-full">
                            {{ $gym->map_url ? 'Get directions' : 'Find on Google Maps' }} <x-ui.icon name="external" class="size-4" />
                        </a>
                    </div>
                </div>

                <div class="gp-reveal gp-card p-6 sm:p-8" style="--d: 200ms">
                    <h3 class="text-lg font-semibold text-white">Contact</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        @if ($gym->phone)
                            <li><a href="tel:{{ $gym->phone }}" class="group flex items-center gap-3 text-gym-light hover:text-gym-orange"><span class="flex size-9 items-center justify-center rounded-lg bg-white/5 text-gym-orange"><x-ui.icon name="phone" class="size-4" /></span>{{ $gym->phone }}</a></li>
                        @endif
                        @if ($whatsapp)
                            <li><a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="group flex items-center gap-3 text-gym-light hover:text-gym-orange"><span class="flex size-9 items-center justify-center rounded-lg bg-white/5 text-gym-orange"><x-ui.icon name="inbox" class="size-4" /></span>WhatsApp us</a></li>
                        @endif
                        @if ($gym->email)
                            <li><a href="mailto:{{ $gym->email }}" class="group flex min-w-0 items-center gap-3 text-gym-light hover:text-gym-orange"><span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/5 text-gym-orange"><x-ui.icon name="mail" class="size-4" /></span><span class="truncate">{{ $gym->email }}</span></a></li>
                        @endif
                    </ul>
                    @if ($socialLinks->isNotEmpty())
                        <div class="mt-5 flex flex-wrap gap-2 border-t border-white/10 pt-5">
                            @foreach ($socialLinks as $link)
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-gym-border px-3 py-1.5 text-xs font-medium text-gym-light transition hover:border-gym-orange hover:text-gym-orange">{{ $link['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
