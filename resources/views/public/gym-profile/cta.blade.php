{{-- Final call to action. --}}
<section class="px-4 py-16 sm:px-6 lg:px-8">
    <div class="gp-reveal relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-gym-orange px-6 py-14 text-center sm:px-12 sm:py-20">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_20%_0%,rgb(255_255_255/0.25),transparent_50%),radial-gradient(ellipse_at_90%_100%,rgb(0_0_0/0.25),transparent_55%)]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -bottom-16 text-gym-black/10">
            <x-ui.icon name="dumbbell" class="size-72 -rotate-12" />
        </div>
        <div class="relative">
            <p class="font-mono text-xs font-semibold tracking-[0.2em] text-gym-black/70 uppercase">{{ $location ?: $gym->name }}</p>
            <h2 class="gp-display mx-auto mt-4 max-w-4xl text-5xl text-gym-black sm:text-7xl">Ready to start at {{ $gym->name }}?</h2>
            <p class="mx-auto mt-5 max-w-xl text-lg font-medium text-gym-black/75">Join online today and pick your plan at the front desk.</p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <a href="{{ $joinUrl }}" class="gp-btn gp-btn-dark px-8">Join now <x-ui.icon name="arrow-right" class="size-5" /></a>
                @if ($gym->phone)
                    <a href="tel:{{ $gym->phone }}" class="gp-btn border border-gym-black/30 px-8 text-gym-black hover:-translate-y-0.5 hover:bg-gym-black/10"><x-ui.icon name="phone" class="size-4" /> Call {{ $gym->phone }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
