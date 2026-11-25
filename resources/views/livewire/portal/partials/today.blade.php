{{--
    Today's visit: check in / check out (when the gym allows self check-in), or the visit summary.
    $todayVisit  AttendanceService::today()   $selfCheckIn  bool   $qrLink  show a link to the QR page
--}}
<div class="pm-glass rounded-2xl p-4">
    <div class="mb-3 flex items-center justify-between text-sm">
        <p class="font-semibold text-gray-900">Today</p>
        <p class="text-gray-500">{{ now()->format('D, d M') }}</p>
    </div>
    @error('member') <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror
    @if ($todayVisit && $todayVisit->check_out_at)
        <div class="flex items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            <x-ui.icon name="check" class="size-5" /> Visit done today · {{ $todayVisit->check_in_at->format('h:i A') }} – {{ $todayVisit->check_out_at->format('h:i A') }}
        </div>
    @elseif ($todayVisit)
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <p class="flex flex-1 items-center gap-2 text-sm font-semibold text-emerald-700">
                <span class="relative flex size-2.5"><span class="absolute inset-0 animate-ping rounded-full bg-emerald-500/70 motion-reduce:animate-none"></span><span class="relative size-2.5 rounded-full bg-emerald-500"></span></span>
                Checked in at {{ $todayVisit->check_in_at->format('h:i A') }}
            </p>
            <button type="button" x-data x-on:click="$dispatch('scan-qr', 'checkOut')" class="btn-secondary rounded-full px-6 py-2.5">
                <x-ui.icon name="logout" class="size-5" /> Check out
            </button>
        </div>
    @elseif ($selfCheckIn)
        <button type="button" x-data x-on:click="$dispatch('scan-qr', 'checkIn')" class="btn-primary w-full rounded-full py-3.5 text-base font-bold tracking-wider uppercase">
            <x-ui.icon name="qr" class="size-5" />
            Check in now
        </button>
    @elseif ($qrLink ?? false)
        <a href="{{ route('portal.check-in') }}" class="flex items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-sm text-gray-600 transition hover:text-brand-600 dark:bg-ink-800">
            <span>Show your QR code at the front desk to check in.</span>
            <span class="inline-flex shrink-0 items-center gap-1 font-semibold text-brand-600"><x-ui.icon name="qr" class="size-4" /> Open QR</span>
        </a>
    @else
        <p class="rounded-xl bg-white px-4 py-3 text-center text-sm text-gray-600 dark:bg-ink-800">Show your QR code at the front desk to check in.</p>
    @endif

    <x-attendance.scanner />
</div>
