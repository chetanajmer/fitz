{{-- Light / dark panel theme switch. State lives in the "theme" cookie; resources/js/app.js handles the click. --}}
<button type="button" data-theme-toggle aria-pressed="{{ panel_theme() === 'dark' ? 'true' : 'false' }}"
        {{ $attributes->merge(['class' => 'flex size-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-brand-600/50 hover:text-brand-600 dark:border-ink-700 dark:bg-ink-800 dark:text-zinc-300 dark:hover:text-brand-400']) }}
        title="Switch light / dark theme">
    <span class="sr-only">Toggle dark theme</span>
    <x-ui.icon name="moon" class="size-5 dark:hidden" />
    <x-ui.icon name="sun" class="hidden size-5 dark:block" />
</button>
