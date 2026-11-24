{{--
    Member experience spotlight.
    NOTE: AI Body Transformation is not implemented in this codebase yet; it is shown as a live
    feature at the product owner's request. QR self-registration is live (/register/{token}).
    Figures in the visuals are illustrative.
--}}
<section id="members" data-lp-section class="relative overflow-hidden py-14 sm:py-20">
    <div aria-hidden="true" data-parallax="0.3" class="pointer-events-none absolute top-40 -left-40 size-[32rem] rounded-full bg-brand-600/10 blur-3xl"></div>
    <div aria-hidden="true" data-parallax="-0.25" class="pointer-events-none absolute right-0 bottom-20 size-[26rem] rounded-full bg-rose-900/20 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="lp-reveal text-sm font-semibold tracking-[0.2em] text-brand-500 uppercase">For your members</p>
            <h2 data-split class="lp-reveal mt-3 text-3xl font-bold tracking-tight sm:text-5xl" style="--d: 80ms">Give members a reason to stay</h2>
            <p class="lp-reveal mt-5 text-zinc-400" style="--d: 160ms">From the moment they scan your QR to the day they hit their goal — Gymai keeps members engaged.</p>
        </div>

        {{-- AI Body Transformation --}}
        <div class="mt-10 grid items-center gap-12 lg:grid-cols-2">
            <div class="lp-reveal order-2 lg:order-1" style="--d: 100ms">
                <span class="inline-flex items-center gap-2 rounded-full border border-brand-600/30 bg-brand-600/10 px-3 py-1 text-xs font-semibold text-brand-400"><x-ui.icon name="sparkles" class="size-4" /> AI powered</span>
                <h3 class="mt-5 text-2xl font-bold tracking-tight sm:text-4xl">AI Body Transformation</h3>
                <p class="mt-4 leading-relaxed text-zinc-400">Show every member what their goal can look like. Gymai's AI creates a personalised body transformation preview and tracks their progress towards it — the motivation that turns new joiners into long-term members.</p>
                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['sparkles', 'Personalised transformation preview', 'An AI view of the member\'s goal physique, based on their body and fitness goal.'],
                        ['trending', 'Progress tracking', 'Weight, body measurements and milestones tracked over time, so members see the change.'],
                        ['whistle', 'Linked to plans & trainers', 'Pairs with the diet charts and workout plans your trainers assign.'],
                    ] as [$icon, $title, $text])
                        <li class="flex gap-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-600/15 text-brand-400"><x-ui.icon :name="$icon" class="size-5" /></span>
                            <span><span class="block font-semibold">{{ $title }}</span><span class="mt-0.5 block text-sm text-zinc-400">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lp-reveal order-1 lg:order-2" style="--d: 200ms" aria-hidden="true">
                <div data-tilt-card class="relative rounded-3xl border border-white/10 bg-gradient-to-br from-ink-800 to-ink-900 p-6 shadow-[0_40px_120px_-40px_rgb(238_68_68/0.45)] sm:p-8">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold">Transformation preview</p>
                        <span class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs text-emerald-400">On track</span>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-4">
                        @foreach ([['Today', 1.18, 'from-zinc-500 to-zinc-700', 'text-zinc-400'], ['Goal · 12 weeks', 0.92, 'from-brand-500 to-rose-800', 'text-brand-400']] as $i => [$label, $width, $gradient, $tone])
                            <div class="relative flex flex-col items-center rounded-2xl border border-ink-700 bg-ink-850 px-4 pt-6 pb-4 {{ $i === 1 ? 'shadow-[0_0_40px_-10px_rgb(238_68_68/0.6)]' : '' }}">
                                <svg viewBox="0 0 100 170" class="h-44 w-auto {{ $i === 1 ? 'lp-float' : '' }}">
                                    <defs><linearGradient id="body-{{ $i }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="{{ $i ? '#f05252' : '#71717a' }}" /><stop offset="1" stop-color="{{ $i ? '#9f1239' : '#3f3f46' }}" /></linearGradient></defs>
                                    <g fill="url(#body-{{ $i }})" transform="translate(50 0) scale({{ $width }} 1) translate(-50 0)">
                                        <circle cx="50" cy="17" r="13" />
                                        <path d="M30 36 Q50 30 70 36 L78 80 Q72 84 66 80 L64 100 L62 165 L53 165 L50 108 L47 165 L38 165 L36 100 L34 80 Q28 84 22 80 Z" />
                                    </g>
                                </svg>
                                <p class="mt-3 text-xs font-medium {{ $tone }}">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        @foreach ([['Weight', '82 → 74 kg'], ['Body fat', '24% → 16%'], ['Progress', '38%']] as [$label, $value])
                            <div class="rounded-xl border border-ink-700 bg-ink-850 p-3">
                                <p class="text-[11px] tracking-wide text-zinc-500 uppercase">{{ $label }}</p>
                                <p class="mt-1 text-sm font-semibold">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 h-2 rounded-full bg-ink-700"><div class="h-full w-[38%] rounded-full bg-gradient-to-r from-brand-600 to-brand-400"></div></div>
                </div>
            </div>
        </div>

        {{-- QR self-registration --}}
        <div class="mt-20 grid items-center gap-12 lg:grid-cols-2">
            <div class="lp-reveal flex justify-center" style="--d: 100ms" aria-hidden="true">
                <div class="relative">
                    <div class="absolute -inset-8 rounded-[3rem] bg-brand-600/20 blur-3xl"></div>
                    <div class="lp-float-slow relative w-64 rounded-[2.5rem] border-[6px] border-ink-700 bg-ink-900 p-4 shadow-2xl sm:w-72">
                        <div class="mx-auto mb-4 h-1.5 w-16 rounded-full bg-ink-700"></div>
                        <div class="rounded-2xl bg-gradient-to-br from-brand-600 to-rose-800 p-4 text-center">
                            <p class="text-xs font-semibold">Iron Temple Fitness</p>
                            <p class="text-[10px] text-white/70">Member registration</p>
                        </div>
                        <div class="mt-3 space-y-2">
                            @foreach (['Full name', 'Mobile number', 'Fitness goal'] as $field)
                                <div class="rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-[11px] text-zinc-500">{{ $field }}</div>
                            @endforeach
                            <div class="rounded-lg bg-brand-600 py-2 text-center text-xs font-semibold">Register</div>
                        </div>
                        <div class="mt-4 flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3">
                            <x-ui.icon name="check" class="size-5 text-emerald-400" />
                            <div><p class="text-[11px] font-semibold text-emerald-300">You're registered!</p><p class="text-[10px] text-zinc-400">Member ID · M0249</p></div>
                        </div>
                    </div>
                    <div class="lp-float absolute -bottom-6 -left-12 rounded-2xl border border-white/10 bg-white p-2.5 shadow-2xl">
                        <svg viewBox="0 0 21 21" class="size-20" shape-rendering="crispEdges">
                            @foreach ([[0,0],[14,0],[0,14]] as [$x, $y])
                                <rect x="{{ $x }}" y="{{ $y }}" width="7" height="7" fill="#111" /><rect x="{{ $x + 1 }}" y="{{ $y + 1 }}" width="5" height="5" fill="#fff" /><rect x="{{ $x + 2 }}" y="{{ $y + 2 }}" width="3" height="3" fill="#111" />
                            @endforeach
                            @foreach ([[9,1],[10,3],[8,5],[11,8],[9,9],[13,10],[15,9],[17,11],[19,9],[9,12],[11,14],[14,13],[16,15],[18,17],[10,17],[12,19],[15,19],[19,19],[8,8],[3,9],[5,11],[1,10]] as [$x, $y])
                                <rect x="{{ $x }}" y="{{ $y }}" width="2" height="2" fill="#111" />
                            @endforeach
                        </svg>
                    </div>
                </div>
            </div>

            <div class="lp-reveal" style="--d: 200ms">
                <span class="inline-flex items-center gap-2 rounded-full border border-ink-700 bg-white/[0.03] px-3 py-1 text-xs font-semibold text-zinc-300"><x-ui.icon name="qr" class="size-4 text-brand-400" /> Scan & join</span>
                <h3 class="mt-5 text-2xl font-bold tracking-tight sm:text-4xl">Members register themselves with a QR code</h3>
                <p class="mt-4 leading-relaxed text-zinc-400">Print your gym's QR code and place it at the front desk. New members scan it with their phone camera, fill a short form and get their member ID instantly — your staff just assigns a plan.</p>
                <ol class="mt-8 space-y-5">
                    @foreach ([['Scan the QR', 'Any phone camera opens your branded registration page.'], ['Fill the form', 'Name, mobile, goal, emergency contact and health notes.'], ['Get a member ID', 'The member is added to your dashboard instantly. Assign a plan and collect the fee.']] as $i => [$title, $text])
                        <li class="flex gap-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold">{{ $i + 1 }}</span>
                            <span><span class="block font-semibold">{{ $title }}</span><span class="mt-0.5 block text-sm text-zinc-400">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-8 flex items-center gap-2 text-sm text-zinc-500"><x-ui.icon name="shield" class="size-4 text-brand-500" /> Each gym gets its own secret QR, and duplicate mobile numbers are blocked.</p>
            </div>
        </div>
    </div>
</section>
