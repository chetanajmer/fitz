@php
    // Month calendar: blanks before the 1st (weeks start on Monday), then each day of the month.
    $daysInMonth = $monthStart->daysInMonth;
    $leading = $monthStart->dayOfWeekIso - 1;
    $today = now()->toDateString();
@endphp
<div class="space-y-6">
    <x-ui.page-header title="QR check-in" subtitle="Scan the QR at the front desk to check in or out." />

    @if ($pendingDeskToken)
        {{-- Arrived from a phone-camera scan of the desk QR: record it once, then drop the token from the address bar. --}}
        <div wire:key="desk-{{ $pendingDeskToken }}" x-data x-init="history.replaceState(null, '', @js(route('portal.check-in'))); $wire.scanDeskQr(@js($pendingDeskToken))"
            class="flex items-center gap-3 rounded-2xl bg-brand-600/10 px-4 py-3 text-sm font-semibold text-brand-600">
            <x-ui.icon name="qr" class="size-5" /> Recording your visit…
        </div>
    @endif

    {{-- Desk QR scanner --}}
    <section class="pm-rise card" x-data="qrScanner({ onScan: (text) => $wire.scanDeskQr(text) })">
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-center">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-600/10 text-brand-600"><x-ui.icon name="qr" class="size-6" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="card-title">Scan the gym's QR</h2>
                <p class="text-sm text-gray-500">Point your camera at the QR code at the front desk. First scan checks you in, next scan checks you out.</p>
                <p x-show="error" x-text="error" x-cloak class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
            </div>
            <button type="button" x-on:click="start()" wire:loading.attr="disabled" wire:target="scanDeskQr" class="btn-primary shrink-0 rounded-full px-6 py-3 font-bold">
                <x-ui.icon name="qr" class="size-5" />
                <span wire:loading.remove wire:target="scanDeskQr">{{ $todayVisit && ! $todayVisit->check_out_at ? 'Scan to check out' : 'Scan to check in' }}</span>
                <span wire:loading wire:target="scanDeskQr">Saving…</span>
            </button>
        </div>

        <template x-teleport="body">
            <div x-show="open" x-cloak x-transition.opacity x-on:keydown.escape.window="stop()" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Scan the gym QR code">
                <div class="relative w-full max-w-sm overflow-hidden rounded-3xl bg-black ring-1 ring-white/10">
                    <video x-ref="video" playsinline muted class="aspect-square w-full object-cover"></video>
                    <div aria-hidden="true" class="pointer-events-none absolute inset-10 rounded-2xl border-2 border-white/80 shadow-[0_0_0_999px_rgb(0_0_0/0.35)]"></div>
                </div>
                <p class="mt-4 text-center text-sm text-white/80">Hold the QR code inside the frame.</p>
                <button type="button" x-on:click="stop()" class="mt-4 rounded-full bg-white/10 px-6 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/20">Cancel</button>
            </div>
        </template>
    </section>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-2">
            <div class="pm-rise" style="--d: 80ms">
                @include('livewire.portal.partials.today')
            </div>

            <div class="grid grid-cols-3 gap-4">
                @foreach ([['Total visits', $totalVisits, 'calendar'], ['This month', count($visitedThisMonth), 'fire'], ['Day streak', $streak['current_streak'], 'trophy']] as $i => [$label, $value, $icon])
                    <div class="pm-rise card card-body text-center" style="--d: {{ 120 + $i * 50 }}ms">
                        <x-ui.icon :name="$icon" class="mx-auto size-5 text-brand-600" />
                        <p class="mt-2 text-2xl font-bold text-gray-900" data-count="{{ $value }}">{{ $value }}</p>
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- This month --}}
        <div class="lg:col-span-3">
            <div class="pm-rise card" style="--d: 260ms">
                <div class="card-header"><h2 class="card-title">{{ $monthStart->format('F Y') }}</h2><span class="text-xs text-gray-500">{{ count($visitedThisMonth) }} {{ \Illuminate\Support\Str::plural('visit', count($visitedThisMonth)) }}</span></div>
                <div class="card-body">
                    <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-medium text-gray-500">
                        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d)<span>{{ $d }}</span>@endforeach
                    </div>
                    <div class="mt-2 grid grid-cols-7 gap-1.5">
                        @for ($i = 0; $i < $leading; $i++)<span></span>@endfor
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $date = $monthStart->copy()->day($d)->toDateString();
                                $visited = in_array($date, $visitedThisMonth, true);
                            @endphp
                            <span @class([
                                'flex h-9 items-center justify-center rounded-lg text-xs font-semibold transition sm:h-10',
                                'bg-brand-600 text-white shadow-[0_6px_14px_-6px_color-mix(in_srgb,var(--gym-orange)_80%,transparent)]' => $visited,
                                'ring-2 ring-brand-500 text-gray-900' => ! $visited && $date === $today,
                                'bg-gray-50 text-gray-400' => ! $visited && $date !== $today,
                            ]) title="{{ \Illuminate\Support\Carbon::parse($date)->format('D, d M') }}{{ $visited ? ' · visited' : '' }}">{{ $d }}</span>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="pm-reveal card">
        <div class="card-header"><h2 class="card-title">Recent check-ins</h2><span class="text-xs text-gray-500">Last {{ $attendance->count() }}</span></div>
        <ul class="divide-y divide-gray-100">
            @forelse ($attendance as $a)
                <li wire:key="a-{{ $a->id }}" class="flex items-center gap-3 px-5 py-2.5 text-sm">
                    <span class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-50 leading-none text-indigo-600">
                        <span class="text-[10px] font-semibold uppercase">{{ $a->date->format('M') }}</span>
                        <span class="text-sm font-bold">{{ $a->date->format('d') }}</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-gray-900">{{ $a->date->format('l') }}</p>
                        <p class="text-xs text-gray-500">{{ ['qr' => 'QR scan', 'self' => 'Self check-in', 'desk_qr' => 'Desk QR scan'][$a->method] ?? 'Front desk' }}{{ $a->check_out_at ? ' · '.$a->durationLabel() : '' }}</p>
                    </div>
                    <span class="text-gray-500">{{ $a->check_in_at->format('h:i A') }} – {{ $a->check_out_at?->format('h:i A') ?? '…' }}</span>
                </li>
            @empty
                <li><x-ui.empty message="No visits recorded yet." icon="calendar" /></li>
            @endforelse
        </ul>
    </div>
</div>
