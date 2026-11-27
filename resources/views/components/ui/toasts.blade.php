{{--
    App-wide toast stack (top-right), rendered once in the base layout. Fired by:
    - session flashes: success, error, warning, info
    - Livewire: $this->dispatch('notify', message: 'Saved.', type: 'success', title: 'Optional title')
    - JS: window.toast('Your changes are being processed.', 'info')
    Types: success (green), error (red), warning (amber), info (blue). Each one closes itself after a few seconds.
--}}
<div x-data="{
        toasts: [],
        titles: { success: 'Success', error: 'Error', warning: 'Warning', info: 'Information' },
        add(message, type = 'success', title = null) {
            type = this.titles[type] ? type : 'info';
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type, title: title || this.titles[type], shown: true });
            setTimeout(() => this.dismiss(id), type === 'error' ? 7000 : 4500);
        },
        dismiss(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) toast.shown = false;
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 250);
        }
    }"
    x-init="
        @foreach (['success', 'error', 'warning', 'info'] as $type)
            @if (session($type)) add(@js(session($type)), @js($type)); @endif
        @endforeach
    "
    x-on:notify.window="add($event.detail.message, $event.detail.type ?? 'success', $event.detail.title ?? null)"
    class="pointer-events-none fixed inset-x-0 top-4 z-[90] flex flex-col items-center gap-3 px-4 sm:items-end sm:pr-6 no-print"
    aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.shown"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-x-6 opacity-0"
             :role="toast.type === 'error' ? 'alert' : 'status'"
             class="pointer-events-auto relative flex w-full max-w-sm items-start gap-3 overflow-hidden rounded-2xl border border-gray-200 bg-surface py-3.5 pr-10 pl-4 shadow-2xl">
            <span class="absolute inset-y-0 left-0 w-1"
                  :class="{ 'bg-green-500': toast.type === 'success', 'bg-red-500': toast.type === 'error', 'bg-amber-500': toast.type === 'warning', 'bg-blue-500': toast.type === 'info' }"></span>
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full"
                  :class="{ 'bg-green-500/15 text-green-500': toast.type === 'success', 'bg-red-500/15 text-red-500': toast.type === 'error', 'bg-amber-500/15 text-amber-500': toast.type === 'warning', 'bg-blue-500/15 text-blue-500': toast.type === 'info' }">
                <x-ui.icon name="check-circle" class="size-5" x-show="toast.type === 'success'" />
                <x-ui.icon name="x-circle" class="size-5" x-show="toast.type === 'error'" />
                <x-ui.icon name="warning" class="size-5" x-show="toast.type === 'warning'" />
                <x-ui.icon name="info" class="size-5" x-show="toast.type === 'info'" />
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-sm font-bold text-gray-900" x-text="toast.title"></p>
                <p class="mt-0.5 text-sm text-gray-500" x-text="toast.message"></p>
            </div>
            <button type="button" x-on:click="dismiss(toast.id)" class="absolute top-3 right-3 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
