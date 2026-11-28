@php
    $steps = [
        ['user', 'New visitor', 'Scans and fills the registration form. They become a member — assign a plan afterwards from Members.'],
        ['check-circle', 'Registered member', 'Scans with the phone camera or the member app to check in.'],
        ['logout', 'Leaving the gym', 'Scans the same QR again to check out.'],
    ];
@endphp
<div>
    <x-ui.page-header title="QR Code" subtitle="One QR for everything. Print it and place it at your front desk.">
        <x-slot:actions><button type="button" onclick="window.print()" class="btn-secondary no-print"><x-ui.icon name="qr" class="size-4" /> Print</button></x-slot:actions>
    </x-ui.page-header>

    <div class="card mx-auto max-w-4xl overflow-hidden">
        <div class="grid md:grid-cols-2">
            <div class="card-body text-center md:border-r md:border-gray-100">
                <h2 class="card-title">Registration, check-in &amp; check-out</h2>
                <div class="mx-auto mt-4 w-64 [&>svg]:h-auto [&>svg]:w-full">{!! $registerQr !!}</div>
                <p class="mt-2 text-sm font-semibold">{{ $gym->name }}</p>
                <p class="text-xs text-gray-500">Scan to register, check in or check out</p>

                <div class="mt-4 flex gap-2 no-print" x-data="{ copied: false }">
                    <input readonly value="{{ $gym->registerUrl() }}" class="input font-mono text-xs" aria-label="QR link">
                    <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js($gym->registerUrl())); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button>
                </div>
                <div class="mt-3 flex flex-wrap justify-center gap-2 no-print">
                    <a href="{{ route('gym.attendance.register-qr', ['download' => 1]) }}" class="btn-primary btn-sm">Download SVG</a>
                    <a href="{{ $gym->registerUrl() }}" target="_blank" class="btn-secondary btn-sm">Open form</a>
                    @can('gym.settings')
                        <button wire:click="regenerateRegisterLink" data-confirm-title="Replace the QR?" wire:confirm="Generate a new QR? Old printed QR codes will stop working." class="btn-secondary btn-sm">Replace QR</button>
                    @endcan
                </div>
            </div>

            <div class="card-body no-print">
                <h3 class="text-sm font-semibold text-gray-900">How it works</h3>
                <ol class="mt-4 space-y-4">
                    @foreach ($steps as $i => [$icon, $title, $copy])
                        <li class="flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600"><x-ui.icon :name="$icon" class="size-4" /></span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $i + 1 }}. {{ $title }}</p>
                                <p class="mt-0.5 text-sm text-gray-500">{{ $copy }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="hint mt-5">Members who aren't logged in see an "Already a member? Log in" button on the form. After they log in, they are checked in automatically.</p>
            </div>
        </div>
    </div>
</div>
