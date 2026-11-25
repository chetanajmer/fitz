{{-- Camera scanner for the gym's check-in QR. Open it with $dispatch('scan-qr', 'checkIn' | 'checkOut') inside a Livewire component that has those actions. --}}
<div wire:ignore x-data="qrScanner" x-on:scan-qr.window="open($event.detail)">
    <dialog x-ref="dialog" x-on:close="stop()" x-on:click.self="$el.close()" aria-label="Scan the check-in QR"
            class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-sm overflow-hidden rounded-2xl bg-gym-black p-0 text-white shadow-xl backdrop:bg-black/80">
        <div class="flex items-center justify-between px-5 py-4">
            <h3 class="font-display text-2xl leading-none uppercase" x-text="action === 'checkOut' ? 'Scan to check out' : 'Scan to check in'">Scan to check in</h3>
            <button type="button" x-on:click="$refs.dialog.close()" class="rounded p-1 text-gym-muted hover:text-white" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button>
        </div>

        <div class="relative aspect-square w-full bg-black">
            <video x-ref="video" x-show="state === 'scanning' || state === 'starting'" playsinline muted class="size-full object-cover"></video>
            <div x-show="state === 'scanning'" aria-hidden="true" class="scan-frame"><span></span></div>
            <p x-show="state === 'starting'" class="absolute inset-0 flex items-center justify-center text-sm text-gym-muted">Starting the camera…</p>
            <p x-show="state === 'sending'" class="absolute inset-0 flex items-center justify-center text-lg font-semibold">Checking…</p>
            <div x-show="state === 'failed'" class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6 text-center">
                <x-ui.icon name="camera" class="size-10 text-gym-orange" />
                <p class="text-sm" x-text="message"></p>
                <button type="button" x-on:click="start()" class="btn-primary">Try again</button>
            </div>
        </div>

        <p role="status" class="min-h-16 px-5 py-4 text-center text-sm text-gym-light" x-text="state === 'failed' ? '' : (message || 'Point your camera at the check-in QR at the front desk.')"></p>
    </dialog>
</div>
