{{-- Footer, plus a sticky action bar on phones. --}}
<footer class="border-t border-gym-border bg-gym-black pb-24 sm:pb-0">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
        <div>
            <a href="#top" class="flex items-center gap-3">
                @if ($gym->logoUrl())
                    <img src="{{ $gym->logoUrl() }}" alt="" loading="lazy" class="size-11 rounded-xl bg-white object-contain p-0.5">
                @else
                    <span class="flex size-11 items-center justify-center rounded-xl bg-gym-orange text-sm font-bold text-white">{{ $gym->initials() }}</span>
                @endif
                <span class="text-lg font-semibold text-white">{{ $gym->name }}</span>
            </a>
            <p class="mt-4 max-w-sm text-sm text-gym-muted">{{ $tagline ?: ($location ? 'Your gym in '.$location.'.' : 'Train with us.') }}</p>
            @if ($socialLinks->isNotEmpty())
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($socialLinks as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-gym-border px-3 py-1.5 text-xs font-medium text-gym-light transition hover:border-gym-orange hover:text-gym-orange">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <p class="text-sm font-semibold text-white">Explore</p>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($sections as $id => $label)
                    <li><a href="#{{ $id }}" class="text-gym-muted transition hover:text-gym-orange">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <p class="text-sm font-semibold text-white">Members</p>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="{{ $joinUrl }}" class="text-gym-muted transition hover:text-gym-orange">Join this gym</a></li>
                <li><a href="{{ route('login', ['mode' => 'otp']) }}" class="text-gym-muted transition hover:text-gym-orange">Member sign in</a></li>
                <li><a href="{{ route('gyms.browse') }}" class="text-gym-muted transition hover:text-gym-orange">Browse other gyms</a></li>
                @if ($gym->phone)<li><a href="tel:{{ $gym->phone }}" class="text-gym-muted transition hover:text-gym-orange">{{ $gym->phone }}</a></li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-gym-border">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-xs text-gym-muted sm:px-6 lg:px-8">
            <p>© {{ now()->year }} {{ $gym->name }}</p>
            <p>Powered by <a href="{{ route('home') }}" class="font-semibold text-gym-light hover:text-gym-orange">Gymai</a></p>
        </div>
    </div>
</footer>

{{-- Phones: the two actions people need most, always in reach. --}}
<div class="fixed inset-x-0 bottom-0 z-40 border-t border-gym-border bg-gym-black-deep/90 p-3 backdrop-blur-xl sm:hidden">
    <div class="grid {{ $gym->phone ? 'grid-cols-2' : 'grid-cols-1' }} gap-3">
        @if ($gym->phone)<a href="tel:{{ $gym->phone }}" class="gp-btn gp-btn-ghost min-h-12"><x-ui.icon name="phone" class="size-4" /> Call</a>@endif
        <a href="{{ $joinUrl }}" class="gp-btn gp-btn-solid min-h-12">Join now</a>
    </div>
</div>
