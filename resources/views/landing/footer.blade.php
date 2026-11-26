<footer id="contact" data-lp-section class="relative overflow-hidden">
    {{-- Final call to action --}}
    {{-- Orange accent band: black type and black buttons on the brand orange. --}}
    <div class="bg-gym-orange text-gym-black [--color-volt:var(--gym-black)]">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <p class="lp-mono">Ready when you are</p>
            <h2 data-lp-slam class="lp-display mt-4 text-[clamp(3.5rem,13vw,12rem)]">Your gym. <span class="lp-hl text-gym-orange">Lifted.</span></h2>
            <div class="mt-10 flex flex-wrap items-center gap-4">
                <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-solid border-gym-black bg-gym-black text-gym-white shadow-none hover:border-gym-charcoal hover:bg-gym-charcoal">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                <a href="{{ route('member.join') }}" class="lp-btn lp-btn-ghost hover:border-gym-black hover:bg-gym-black hover:text-gym-white">Register as Member</a>
                <span class="font-bold">{{ $priceLabel }} per active member / month · no setup fee</span>
            </div>
        </div>
    </div>

    <div class="bg-forest text-chalk">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.3fr] lg:px-8">
            <div>
                <a href="#home" aria-label="Fitnix home"><x-brand.logo size="lg" /></a>
                <p class="mt-4 max-w-xs text-sm text-chalk/70">All-in-one gym management software for memberships, payments, attendance, trainers and growth — built for independent gyms and studios.</p>
            </div>
            <nav aria-label="Quick links">
                <h3 class="lp-mono text-volt">Explore</h3>
                <ul class="mt-4 space-y-2.5 text-sm font-semibold">
                    @foreach (['home' => 'Home', 'how-it-works' => 'How it works', 'about' => 'About us', 'faq' => 'FAQ'] as $id => $label)
                        <li><a href="#{{ $id }}" class="hover:text-volt">{{ $label }}</a></li>
                    @endforeach
                    <li><a href="{{ route('gyms.browse') }}" class="hover:text-volt">Browse gyms</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-volt">Login</a></li>
                </ul>
            </nav>
            <nav aria-label="Product">
                <h3 class="lp-mono text-volt">Product</h3>
                <ul class="mt-4 space-y-2.5 text-sm font-semibold">
                    <li><a href="#features" class="hover:text-volt">Features</a></li>
                    <li><a href="#members" class="hover:text-volt">Member app</a></li>
                    <li><a href="#ai-coach" class="hover:text-volt">AI Coach</a></li>
                    <li><a href="#pricing" class="hover:text-volt">Pricing</a></li>
                    @if ($registrationsOpen)
                        <li><a href="{{ route('register.gym') }}" class="hover:text-volt">Register Your Gym</a></li>
                    @endif
                    <li><a href="{{ route('member.join') }}" class="hover:text-volt">Register as Member</a></li>
                </ul>
            </nav>
            <div>
                <h3 class="lp-mono text-volt">Contact</h3>
                <ul class="mt-4 space-y-3 text-sm font-semibold">
                    @if ($contact['email'])
                        <li><a href="mailto:{{ $contact['email'] }}" class="flex items-center gap-2.5 hover:text-volt"><x-ui.icon name="mail" class="size-4 text-volt" /> {{ $contact['email'] }}</a></li>
                    @endif
                    @if ($contact['phone'])
                        <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="flex items-center gap-2.5 hover:text-volt"><x-ui.icon name="phone" class="size-4 text-volt" /> {{ $contact['phone'] }}</a></li>
                    @endif
                    @if ($contact['address'])
                        <li class="flex gap-2.5"><x-ui.icon name="building" class="size-4 shrink-0 text-volt" /> <span>{{ $contact['address'] }}</span></li>
                    @endif
                </ul>
            </div>
        </div>
        <p aria-hidden="true" class="lp-display lp-outline -mb-[0.12em] text-center text-[27vw] select-none" style="--lp-stroke: rgb(245 245 245 / 0.18)">Fitnix</p>
        <div class="border-t-2 border-chalk/15">
            <div class="lp-mono mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-5 text-chalk/60 sm:flex-row sm:px-6 lg:px-8">
                <p>© {{ now()->year }} Fitnix. All rights reserved.</p>
                <p>Made for gyms in India</p>
            </div>
        </div>
    </div>
</footer>
