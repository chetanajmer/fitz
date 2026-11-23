<section class="relative px-4 pb-24 sm:px-6 lg:px-8">
    <div class="lp-reveal relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] border border-brand-600/30 bg-gradient-to-br from-brand-700 via-brand-600 to-rose-900 px-6 py-16 text-center sm:px-16 sm:py-20">
        <div aria-hidden="true" class="absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.07)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.07)_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_70%)]"></div>
        <div aria-hidden="true" class="lp-float absolute -top-20 -right-16 size-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative">
            <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-5xl">Ready to run a smarter gym?</h2>
            <p class="mx-auto mt-5 max-w-xl text-white/80">Set up your gym in minutes. No setup fee — pay only {{ $priceLabel }} per active member per month.</p>
            <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ $ctaUrl }}" class="btn bg-white px-7 py-3.5 text-base font-semibold text-brand-700 shadow-xl hover:bg-zinc-100">{{ $ctaLabel }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                <a href="#pricing" class="btn border border-white/40 px-7 py-3.5 text-base text-white hover:bg-white/10">See pricing</a>
            </div>
        </div>
    </div>
</section>
