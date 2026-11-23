@php
    $primary = $gym->primaryColor();
    $secondary = $gym->secondaryColor();
    $hours = $gym->setting('opening_hours', []);
    $facilities = $gym->setting('facilities', []);
    $social = array_filter($gym->setting('social', []));
@endphp
<x-layouts.base :title="$gym->name" body-class="bg-white">
    <x-slot:head>
        <meta name="description" content="{{ \Illuminate\Support\Str::limit($gym->description ?: $gym->name.' — '.$gym->city, 155) }}">
    </x-slot:head>

    {{-- Hero --}}
    <header class="relative overflow-hidden bg-cover bg-center text-white" style="background-image: {{ $gym->bannerUrl() ? 'linear-gradient(135deg, '.$primary.'cc 0%, '.$secondary.'99 100%), url('.e($gym->bannerUrl()).')' : 'linear-gradient(135deg, '.$primary.' 0%, '.$secondary.' 100%)' }}">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:py-20">
            <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                @if ($gym->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="{{ $gym->name }} logo" class="size-24 rounded-2xl bg-white object-contain p-1.5 shadow-lg">
                @else
                    <div class="flex size-24 items-center justify-center rounded-2xl bg-white/20 text-3xl font-bold">{{ $gym->initials() }}</div>
                @endif
                <div>
                    <h1 class="text-3xl font-bold sm:text-5xl">{{ $gym->name }}</h1>
                    @if ($gym->setting('tagline'))<p class="mt-2 text-lg text-white/85">{{ $gym->setting('tagline') }}</p>@endif
                    @if ($gym->city)<p class="mt-2 text-sm text-white/75">📍 {{ trim($gym->address.', '.$gym->city, ', ') }}</p>@endif
                </div>
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="#enquire" class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold shadow" style="color: {{ $primary }}">Enquire now</a>
                @if ($gym->phone)<a href="tel:{{ $gym->phone }}" class="rounded-lg bg-white/15 px-5 py-2.5 text-sm font-semibold ring-1 ring-white/40 hover:bg-white/25">Call {{ $gym->phone }}</a>@endif
                @if (! empty($social['whatsapp']))<a href="https://wa.me/{{ preg_replace('/\D/', '', $social['whatsapp']) }}" target="_blank" rel="noopener" class="rounded-lg bg-white/15 px-5 py-2.5 text-sm font-semibold ring-1 ring-white/40 hover:bg-white/25">WhatsApp</a>@endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-16 px-4 py-14">
        <div class="grid gap-10 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <h2 class="text-2xl font-bold">About us</h2>
                <p class="mt-4 leading-relaxed whitespace-pre-line text-gray-600">{{ $gym->description ?: 'Welcome to '.$gym->name.'. Visit us to start your fitness journey!' }}</p>

                @if ($facilities)
                    <h3 class="mt-10 text-lg font-semibold">Facilities</h3>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($facilities as $f)
                            <li class="flex items-center gap-3 rounded-lg bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700">
                                <span class="flex size-6 items-center justify-center rounded-full text-white" style="background: {{ $primary }}"><x-ui.icon name="check" class="size-4" /></span>{{ $f }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <aside class="space-y-6">
                <div class="rounded-2xl border border-gray-200 p-6">
                    <h3 class="font-semibold">Opening hours</h3>
                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ($hours as $day => $h)
                            <div class="flex justify-between {{ strtolower(now()->format('l')) === $day ? 'font-semibold' : '' }}">
                                <dt class="capitalize text-gray-600">{{ $day }}</dt>
                                <dd>{{ $h['closed'] ? 'Closed' : \Illuminate\Support\Carbon::createFromFormat('H:i', $h['open'])->format('g:i A').' – '.\Illuminate\Support\Carbon::createFromFormat('H:i', $h['close'])->format('g:i A') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <div class="rounded-2xl border border-gray-200 p-6 text-sm">
                    <h3 class="font-semibold">Contact</h3>
                    <ul class="mt-4 space-y-2 text-gray-600">
                        @if ($gym->phone)<li>📞 <a href="tel:{{ $gym->phone }}" class="hover:underline">{{ $gym->phone }}</a></li>@endif
                        @if ($gym->email)<li>✉️ <a href="mailto:{{ $gym->email }}" class="hover:underline">{{ $gym->email }}</a></li>@endif
                        @if ($gym->address || $gym->city)<li>📍 {{ trim($gym->address.', '.$gym->city, ', ') }}</li>@endif
                    </ul>
                    @if ($social)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (['website' => 'Website', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'] as $key => $label)
                                @if (! empty($social[$key]) && filter_var($social[$key], FILTER_VALIDATE_URL))
                                    <a href="{{ $social[$key] }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-gray-200 px-3 py-1 text-xs font-medium hover:bg-gray-50">{{ $label }}</a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </aside>
        </div>

        @if ($photos->isNotEmpty())
            <section>
                <h2 class="text-2xl font-bold">Gallery</h2>
                <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($photos as $p)
                        <figure class="overflow-hidden rounded-xl bg-gray-100">
                            <img src="{{ $p->url() }}" alt="{{ $p->caption ?: $gym->name }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-105">
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($plans->isNotEmpty())
            <section>
                <h2 class="text-2xl font-bold">Membership plans</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($plans as $plan)
                        <div class="flex flex-col rounded-2xl border border-gray-200 p-6 shadow-sm">
                            <h3 class="font-semibold">{{ $plan->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $plan->durationLabel() }}</p>
                            <p class="mt-4 text-4xl font-bold" style="color: {{ $primary }}">{{ money($plan->price, false) }}</p>
                            @if ($plan->description)<p class="mt-3 text-sm text-gray-600">{{ $plan->description }}</p>@endif
                            @if ($plan->features)
                                <ul class="mt-4 space-y-2 text-sm text-gray-600">
                                    @foreach ($plan->features as $f)<li class="flex gap-2"><span style="color: {{ $primary }}">✓</span>{{ $f }}</li>@endforeach
                                </ul>
                            @endif
                            <a href="#enquire" class="mt-6 block rounded-lg py-2.5 text-center text-sm font-semibold text-white" style="background: {{ $primary }}">Enquire</a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section id="enquire" class="mx-auto max-w-xl rounded-2xl border border-gray-200 p-6 shadow-sm sm:p-8">
            <h2 class="text-2xl font-bold">Get in touch</h2>
            <p class="mb-6 text-sm text-gray-500">Leave your details and {{ $gym->name }} will contact you.</p>
            <livewire:public-site.enquiry-form :gym="$gym" source="website" />
        </section>
    </main>

    <footer class="border-t border-gray-100 py-8 text-center text-xs text-gray-400">© {{ now()->year }} {{ $gym->name }} · Powered by <a href="{{ route('home') }}" class="hover:underline">Gymai</a></footer>
</x-layouts.base>
