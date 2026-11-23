@php($price = \App\Models\PricingRate::rateOn())
<x-layouts.base title="Gym management software" body-class="bg-white">
    <header class="border-b border-gray-100">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <div class="flex size-9 items-center justify-center rounded-xl bg-indigo-600 font-bold text-white">G</div>
                <span class="text-lg font-semibold">Gymai</span>
            </a>
            <nav class="flex items-center gap-2">
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn-primary">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
                    <a href="{{ route('register.gym') }}" class="btn-primary">Register your gym</a>
                @endauth
            </nav>
        </div>
    </header>

    <section class="bg-gradient-to-b from-indigo-50 to-white">
        <div class="mx-auto max-w-6xl px-4 py-20 text-center sm:py-28">
            <p class="mx-auto inline-flex rounded-full bg-white px-3 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-100">No setup fee · No registration fee · All features included</p>
            <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl">Run your gym, not spreadsheets.</h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-600">Members, enquiries, memberships, payments, attendance and trainers — in one simple dashboard. Get your own gym page and QR registration link in minutes.</p>
            <div class="mt-10 flex justify-center gap-3">
                <a href="{{ route('register.gym') }}" class="btn-primary px-6 py-3 text-base">Start free</a>
                <a href="#pricing" class="btn-secondary px-6 py-3 text-base">See pricing</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-20">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['inbox', 'Enquiries & QR sign-ups', 'Visitors scan your QR code to send an enquiry. Track follow-ups and convert them into members in one click.'],
                ['users', 'Member management', 'Profiles, emergency contacts, goals, trainers, membership and payment history — plus bulk CSV/Excel import.'],
                ['card', 'Plans & renewals', 'Monthly, quarterly, half-yearly, yearly or custom plans. See who is expiring and renew instantly.'],
                ['cash', 'Payments & receipts', 'Record cash, UPI, bank transfer or card payments — full or partial — and print receipts and invoices.'],
                ['calendar', 'Attendance', 'Manual or QR check-in and check-out with duplicate protection and daily & monthly reports.'],
                ['whistle', 'Trainers', 'Give trainers their own login with access to only their assigned members.'],
            ] as [$icon, $title, $text])
                <div class="rounded-2xl border border-gray-200 p-6">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><x-ui.icon :name="$icon" class="size-6" /></div>
                    <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="pricing" class="bg-gray-50">
        <div class="mx-auto max-w-6xl px-4 py-20 text-center">
            <h2 class="text-3xl font-bold">One simple price</h2>
            <div class="mx-auto mt-10 max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
                <p class="text-5xl font-bold">{{ money($price, fmod($price, 1) != 0.0) }}</p>
                <p class="mt-2 text-gray-600">per active member / month</p>
                <ul class="mt-8 space-y-3 text-left text-sm text-gray-700">
                    @foreach (['No setup or registration fee', 'Every feature included', 'Pay only for members with a valid membership', 'Enquiries, expired and inactive members are free', 'Monthly invoice with full history'] as $line)
                        <li class="flex gap-2"><x-ui.icon name="check" class="size-5 text-emerald-500" /> {{ $line }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('register.gym') }}" class="btn-primary mt-8 w-full py-3">Register your gym</a>
            </div>
        </div>
    </section>

    <footer class="border-t border-gray-100 py-8 text-center text-xs text-gray-400">© {{ now()->year }} Gymai</footer>
</x-layouts.base>
