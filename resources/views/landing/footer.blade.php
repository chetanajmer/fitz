<footer id="contact" data-lp-section class="border-t border-ink-700 bg-ink-950">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:px-8">
        <div>
            <a href="#home" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 font-bold">G</span>
                <span class="text-lg font-semibold">Gymai</span>
            </a>
            <p class="mt-5 max-w-xs text-sm leading-relaxed text-zinc-500">All-in-one gym management software for memberships, payments, attendance, trainers and growth — built for independent gyms and studios.</p>
        </div>

        <nav aria-label="Quick links">
            <h3 class="text-sm font-semibold">Quick Links</h3>
            <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                @foreach (['home' => 'Home', 'how-it-works' => 'How It Works', 'about' => 'About Us', 'faq' => 'FAQ'] as $id => $label)
                    <li><a href="#{{ $id }}" class="transition hover:text-white">{{ $label }}</a></li>
                @endforeach
                <li><a href="{{ route('login') }}" class="transition hover:text-white">Login</a></li>
            </ul>
        </nav>

        <nav aria-label="Product">
            <h3 class="text-sm font-semibold">Product</h3>
            <ul class="mt-4 space-y-2.5 text-sm text-zinc-500">
                <li><a href="#features" class="transition hover:text-white">Features</a></li>
                <li><a href="#showcase" class="transition hover:text-white">Dashboard</a></li>
                <li><a href="#pricing" class="transition hover:text-white">Subscription Plans</a></li>
                @if ($registrationsOpen)
                    <li><a href="{{ route('register.gym') }}" class="transition hover:text-white">Register Your Gym</a></li>
                @endif
            </ul>
        </nav>

        <div>
            <h3 class="text-sm font-semibold">Contact</h3>
            <ul class="mt-4 space-y-3 text-sm text-zinc-500">
                @if ($contact['email'])
                    <li><a href="mailto:{{ $contact['email'] }}" class="flex items-center gap-2.5 transition hover:text-white"><x-ui.icon name="mail" class="size-4 text-brand-500" /> {{ $contact['email'] }}</a></li>
                @endif
                @if ($contact['phone'])
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="flex items-center gap-2.5 transition hover:text-white"><x-ui.icon name="phone" class="size-4 text-brand-500" /> {{ $contact['phone'] }}</a></li>
                @endif
                @if ($contact['address'])
                    <li class="flex gap-2.5"><x-ui.icon name="building" class="size-4 shrink-0 text-brand-500" /> <span>{{ $contact['address'] }}</span></li>
                @endif
            </ul>
        </div>
    </div>
    <div class="border-t border-ink-700">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-xs text-zinc-600 sm:flex-row sm:px-6 lg:px-8">
            <p>© {{ now()->year }} Gymai. All rights reserved.</p>
            <p>Made for gyms in India 🇮🇳</p>
        </div>
    </div>
</footer>
