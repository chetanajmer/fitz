@php
    $credits = $status['credits'];
    // Text on the gym's accent colour: black or white, whichever reads better.
    $accentInk = \App\Support\Color::luminance(current_gym()?->dashboardColors('member')['primary'] ?? '#ff6b00') > 0.18 ? '#111111' : '#ffffff';
@endphp
<div class="progress-page space-y-6" style="--pp-ink: {{ $accentInk }}">
    {{-- Always black, like the landing page hero. --}}
    <section class="pp-hero relative overflow-hidden rounded-2xl bg-gym-black text-white ring-1 ring-gym-border">
        <img src="{{ asset('images/gym-page/hero.webp') }}" alt="" class="pp-hero-photo pointer-events-none absolute inset-y-0 right-0 h-full w-full object-cover object-top sm:right-40 sm:w-1/2">
        <div class="relative flex flex-wrap items-center gap-x-10 gap-y-6 p-6 sm:p-10">
            <div class="max-w-md min-w-0 flex-1 basis-72">
                <h1 class="font-display text-5xl leading-none uppercase sm:text-7xl">See the <span class="pp-hl">change.</span></h1>
                <p class="mt-4 font-medium text-gym-light/80">Your photos, measurements and AI coach reports, all in one place.</p>

                @if ($status['has_consented'])
                    <p class="mt-5 text-sm text-gym-muted">You accepted the AI terms on {{ \Illuminate\Support\Carbon::parse($status['consented_at'])->format('d M Y') }}.
                        <button type="button" wire:click="setConsent(false)" wire:confirm="Withdraw your consent? All your AI reports and progress photos are deleted and cannot be restored. Your measurements stay, and credits you already used are not refunded." class="underline underline-offset-2 hover:text-white">Withdraw consent</button></p>
                @elseif ($status['available'])
                    <p class="mt-5 text-sm text-gym-light/80">{{ $status['consent_text'] }}</p>
                    <button type="button" wire:click="setConsent(true)" class="btn-primary mt-4">I agree <x-ui.icon name="arrow-right" class="size-4" /></button>
                @else
                    <p class="mt-5 text-sm text-gym-muted">AI coach is not available at the moment. Ask your gym about it. You can still record photos and measurements.</p>
                @endif
            </div>

            @if ($status['has_consented'] && $status['available'])
                <div class="pp-stamp sm:ml-auto" role="img" aria-label="{{ $credits['remaining'] }} of {{ $credits['limit'] }} credits left this month">
                    <p class="font-display text-5xl leading-none">{{ $credits['remaining'] }}<span class="text-2xl text-gym-muted"> / {{ $credits['limit'] }}</span></p>
                    <p class="mt-1 text-xs font-semibold">credits left this month</p>
                    <p class="text-xs text-gym-muted">New ones on {{ \Illuminate\Support\Carbon::parse($credits['resets_on'])->format('d M') }}</p>
                </div>
            @endif
        </div>
    </section>

    <div class="card pp-step">
        @include('livewire.partials.progress.photos', ['photoColumns' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-5'])
    </div>

    @if ($status['features']['photo_comparison']['available'])
        <div class="card pp-step">
            <div class="card-header"><h2 class="card-title">Compare two photos</h2></div>
            <div class="card-body space-y-5">
                @if ($photos->count() < 2)
                    <p class="text-sm text-gray-500">Two photos are all you need. Add two photos to compare them.</p>
                @else
                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach (['beforePhotoId' => 'Before photo', 'afterPhotoId' => 'After photo'] as $field => $label)
                            <fieldset class="min-w-0">
                                <legend class="mb-2 text-sm font-semibold text-gray-900">{{ $label }}</legend>
                                <div class="pp-picks flex gap-3 overflow-x-auto p-1">
                                    @foreach ($photos as $p)
                                        <label wire:key="{{ $field }}-{{ $p->id }}" class="pp-pick">
                                            <input type="radio" wire:model="{{ $field }}" name="{{ $field }}" value="{{ $p->id }}" class="sr-only">
                                            <img src="{{ $photoUrl($p->id) }}" alt="" loading="lazy">
                                            <span>{{ $p->taken_on->format('d M Y') }}<br>{{ \App\Models\ProgressPhoto::POSES[$p->pose] ?? $p->pose }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error($field)<p class="input-error">{{ $message }}</p>@enderror
                            </fieldset>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button type="button" wire:click="requestReport('photo_comparison')" wire:loading.attr="disabled" class="btn-primary px-6 py-3" @disabled(! $status['has_consented'])>
                            <x-ui.icon name="sparkles" class="size-5" /> Compare <span class="font-normal opacity-80">{{ $status['features']['photo_comparison']['credits'] }} {{ \Illuminate\Support\Str::plural('credit', $status['features']['photo_comparison']['credits']) }}</span>
                        </button>
                        <p class="text-sm text-gray-500">{{ $status['has_consented'] ? 'AI coach describes what changed, honestly.' : 'Accept the AI terms above first.' }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="pp-step">
        @include('livewire.partials.progress.reports', ['compareInReports' => false])
    </div>

    <x-progress.transformation :data="$transformation" :photo-url="$photoUrl" />

    <details wire:ignore.self class="card group">
        <summary class="card-header cursor-pointer list-none [&::-webkit-details-marker]:hidden"><h2 class="card-title">Add measurements (optional)</h2><x-ui.icon name="plus" class="size-6 text-brand-600 transition-transform group-open:rotate-45 motion-reduce:transition-none" /></summary>
        @include('livewire.partials.progress.measurements')
    </details>
</div>
