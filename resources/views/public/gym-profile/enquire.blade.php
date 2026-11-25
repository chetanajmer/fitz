{{-- Enquiry form (Livewire) beside quick ways to reach the gym. --}}
<section id="enquire" class="gp-section gp-surface relative overflow-hidden">
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -left-20 size-[32rem] rounded-full bg-gym-orange/15 blur-[130px]"></div>
    <div class="relative mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
        <div>
            <p class="gp-reveal gp-eyebrow">Get in touch</p>
            <h2 class="gp-reveal gp-display mt-4 text-5xl text-white sm:text-6xl" style="--d: 80ms">Talk to <span class="text-gym-orange">the team.</span></h2>
            <p class="gp-reveal mt-5 max-w-md text-lg text-gym-light/80" style="--d: 160ms">Leave your details and {{ $gym->name }} will contact you about plans, timings or a visit.</p>

            <div class="mt-10 grid gap-3 sm:grid-cols-2">
                @if ($gym->phone)
                    <a href="tel:{{ $gym->phone }}" class="gp-reveal gp-card group flex items-center gap-4 p-5">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-gym-orange/15 text-gym-orange transition group-hover:bg-gym-orange group-hover:text-white"><x-ui.icon name="phone" class="size-5" /></span>
                        <span><span class="block text-xs text-gym-muted">Call</span><span class="font-semibold text-white">{{ $gym->phone }}</span></span>
                    </a>
                @endif
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="gp-reveal gp-card group flex items-center gap-4 p-5" style="--d: 80ms">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-gym-orange/15 text-gym-orange transition group-hover:bg-gym-orange group-hover:text-white"><x-ui.icon name="inbox" class="size-5" /></span>
                        <span><span class="block text-xs text-gym-muted">WhatsApp</span><span class="font-semibold text-white">Message us</span></span>
                    </a>
                @endif
                <a href="{{ $gym->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="gp-reveal gp-card group flex items-center gap-4 p-5" style="--d: 160ms">
                    <span class="flex size-12 items-center justify-center rounded-xl bg-gym-orange/15 text-gym-orange transition group-hover:bg-gym-orange group-hover:text-white"><x-ui.icon name="map-pin" class="size-5" /></span>
                    <span><span class="block text-xs text-gym-muted">Visit</span><span class="font-semibold text-white">Get directions</span></span>
                </a>
            </div>
        </div>

        <div class="gp-reveal gp-glass rounded-[1.75rem] p-6 shadow-[0_40px_80px_-40px_rgb(255_107_0/0.5)] sm:p-9" style="--d: 120ms">
            <h3 class="text-2xl font-semibold text-white">Send an enquiry</h3>
            <p class="mt-1 mb-7 text-sm text-gym-muted">It takes less than a minute.</p>
            <livewire:public-site.enquiry-form :gym="$gym" source="website" />
        </div>
    </div>
</section>
