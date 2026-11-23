{{--
    Livewire-driven modal. Usage:
    <x-ui.modal wire-model="showForm" title="Add plan"> ... </x-ui.modal>
    Visibility is bound to the public boolean property on the component.
--}}
@props(['wireModel', 'title' => null, 'maxWidth' => 'lg'])
@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl', '3xl' => 'sm:max-w-3xl'];
@endphp
<div x-data x-cloak x-show="$wire.{{ $wireModel }}" x-on:keydown.escape.window="$wire.{{ $wireModel }} = false"
     class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div x-show="$wire.{{ $wireModel }}" x-transition.opacity class="fixed inset-0 bg-gray-900/50" x-on:click="$wire.{{ $wireModel }} = false"></div>
    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div x-show="$wire.{{ $wireModel }}" x-transition
             class="relative w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} rounded-xl bg-white shadow-xl">
            @if ($title)
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" x-on:click="$wire.{{ $wireModel }} = false">
                        <x-ui.icon name="x" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="p-5">{{ $slot }}</div>
        </div>
    </div>
</div>
