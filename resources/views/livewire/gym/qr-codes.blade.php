<div>
    <x-ui.page-header title="QR Codes" subtitle="Print these and place them at your front desk.">
        <x-slot:actions><button type="button" onclick="window.print()" class="btn-secondary no-print"><x-ui.icon name="qr" class="size-4" /> Print</button></x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto max-w-lg">
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
