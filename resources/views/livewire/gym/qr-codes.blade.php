<div>
    <x-ui.page-header title="QR Codes" subtitle="Print these and place them at your front desk.">
        <x-slot:actions><button type="button" onclick="window.print()" class="btn-secondary no-print"><x-ui.icon name="qr" class="size-4" /> Print</button></x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-start">
        <div class="card card-body text-center">
            <h2 class="card-title">Member check-in</h2>
            <p class="mt-1 text-sm text-gray-500">Members scan this from their member page to check in and out.</p>
            @can('gym.settings')
                <div class="mx-auto mt-4 grid w-full max-w-sm grid-cols-2 gap-2 no-print" role="group" aria-label="Check-in QR type">
                    @foreach (\App\Services\CheckInCodeService::MODES as $mode => $label)
                        <button type="button" wire:click="setCheckinMode('{{ $mode }}')" aria-pressed="{{ $checkinMode === $mode ? 'true' : 'false' }}" class="{{ $checkinMode === $mode ? 'btn-primary' : 'btn-secondary' }} btn-sm">{{ $label }}</button>
                    @endforeach
                </div>
            @endcan
            @if ($checkinMode === \App\Services\CheckInCodeService::PRINTED)
                <div class="mx-auto mt-4 w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $checkinQr !!}</div>
                <p class="mt-2 text-sm font-semibold">{{ $gym->name }}</p>
                <p class="hint no-print">Simple to use, but a photo of it also works away from the gym. Use the live screen QR to prevent that.</p>
                <div class="mt-3 flex flex-wrap justify-center gap-2 no-print">
                    <a href="{{ route('gym.attendance.check-in-qr', ['download' => 1]) }}" class="btn-primary btn-sm">Download SVG</a>
                    @can('gym.settings')
                        <button wire:click="regenerateCheckinQr" wire:confirm="Generate a new check-in QR? Printed copies of the old one will stop working." class="btn-secondary btn-sm">Replace QR</button>
                    @endcan
                </div>
            @else
                <p class="mx-auto mt-4 max-w-sm text-sm text-gray-500">The code changes every {{ \App\Services\CheckInCodeService::SCREEN_SECONDS }} seconds. Keep the screen open on a tablet or PC at the desk; printed check-in QRs are not accepted.</p>
                <a href="{{ route('gym.check-in-screen') }}" target="_blank" class="btn-primary mx-auto mt-4 no-print">Open live screen</a>
            @endif
        </div>

        <div class="card card-body text-center">
            <h2 class="card-title">Member registration</h2>
            <p class="mt-1 text-sm text-gray-500">New members scan this to fill the registration form. They become a <strong>member</strong> — assign a plan afterwards from Members.</p>
            <div class="mx-auto mt-4 w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $registerQr !!}</div>
            <p class="mt-2 text-sm font-semibold">{{ $gym->name }}</p>
            <div class="mt-4 flex gap-2 no-print" x-data="{ copied: false }">
                <input readonly value="{{ $gym->registerUrl() }}" class="input font-mono text-xs">
                <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText('{{ $gym->registerUrl() }}'); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button>
            </div>
            <div class="mt-3 flex justify-center gap-2 no-print">
                <a href="{{ route('gym.attendance.register-qr', ['download' => 1]) }}" class="btn-primary btn-sm">Download SVG</a>
                <a href="{{ $gym->registerUrl() }}" target="_blank" class="btn-secondary btn-sm">Open form</a>
            </div>
            @can('gym.settings')
                <button wire:click="regenerateRegisterLink" wire:confirm="Generate a new registration QR? Old printed QR codes will stop working." class="btn-secondary btn-sm mx-auto mt-3 no-print">Regenerate link</button>
            @endcan
        </div>
    </div>
</div>
