<div wire:poll.keep-alive.10s class="flex min-h-full flex-col items-center justify-center gap-8 bg-gym-black p-6 text-center text-white">
    <div>
        <p class="text-lg font-semibold text-gym-muted">{{ $gym->name }}</p>
        <h1 class="mt-2 font-display text-6xl leading-none uppercase sm:text-8xl">Scan to check in</h1>
    </div>
    @if ($isActive)
        <div class="w-full max-w-md rounded-2xl bg-white p-5 [&>svg]:h-auto [&>svg]:w-full">{!! $qr !!}</div>
        <p class="max-w-md text-lg text-gym-light">Open your member page, tap <strong>Check in now</strong> or <strong>Check out</strong>, and point your camera here. The code changes every {{ \App\Services\CheckInCodeService::SCREEN_SECONDS }} seconds.</p>
    @else
        <p class="max-w-md text-lg text-gym-light">This gym uses the printed QR. Switch to the live screen QR on the QR Code page to use this screen.</p>
    @endif
    <a href="{{ route('gym.qr-codes') }}" class="text-sm text-gym-muted underline underline-offset-2 hover:text-white">Back to QR codes</a>
</div>
