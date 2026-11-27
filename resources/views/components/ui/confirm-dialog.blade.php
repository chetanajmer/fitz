{{--
    App-wide confirmation dialog, rendered once in the base layout. It replaces the browser's native
    confirm / prompt boxes: every `wire:confirm="Delete this?"` opens it (see resources/js/confirm-dialog.js),
    and scripts can call `await window.confirmDialog({ title: 'Delete photo?', message: '...' })`.
    Variants: `wire:confirm.primary` (orange, non-destructive) and `wire:confirm.prompt="Question|expected"`.
--}}
<div x-data="confirmDialog" x-cloak x-show="open"
     x-on:confirm-dialog.window="show($event.detail)"
     x-on:keydown.escape.window="open && cancel()"
     class="fixed inset-0 z-[80] overflow-y-auto no-print"
     role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/70 backdrop-blur-sm" x-on:click="cancel()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="scale-100 opacity-100" x-transition:leave-end="scale-95 opacity-0"
             class="relative w-full max-w-sm rounded-3xl border border-gray-200 bg-surface px-6 pt-8 pb-6 text-center shadow-2xl">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full ring-8"
                  :class="tone === 'danger' ? 'bg-red-500/15 text-red-500 ring-red-500/5' : 'bg-brand-600/15 text-brand-600 ring-brand-600/5'">
                <x-ui.icon name="trash" class="size-7" x-show="icon === 'trash'" />
                <x-ui.icon name="warning" class="size-7" x-show="icon === 'warning'" />
                <x-ui.icon name="question" class="size-7" x-show="icon === 'question'" />
            </span>
            <h2 id="confirm-dialog-title" class="mt-5 text-lg font-bold text-gray-900" x-text="title"></h2>
            <p id="confirm-dialog-message" class="mt-2 text-sm leading-relaxed whitespace-pre-line text-gray-500" x-show="message" x-text="message"></p>
            <template x-if="expected !== null">
                <form class="mt-4 text-left" x-on:submit.prevent="accept()">
                    <label for="confirm-dialog-input" class="text-xs font-medium text-gray-500">Type <strong class="text-gray-900" x-text="expected"></strong> to continue</label>
                    <input id="confirm-dialog-input" type="text" x-model="typed" autocomplete="off" class="input mt-1.5 w-full">
                </form>
            </template>
            <div class="mt-7 flex gap-3">
                <button type="button" x-show="cancelText" x-text="cancelText" x-on:click="cancel()" class="btn-secondary flex-1 rounded-full! py-2.5"></button>
                <button type="button" x-ref="confirm" x-text="confirmText" x-on:click="accept()"
                        :disabled="expected !== null && typed !== expected"
                        class="flex-1 rounded-full! py-2.5"
                        :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'"></button>
            </div>
        </div>
    </div>
</div>
