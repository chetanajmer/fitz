{{--
    Themed file picker: click or drop a file anywhere on it, with drag highlight, upload progress and the chosen
    file's name. The slot is an optional preview shown on the left (or, with cover, filling the area).
    <x-ui.file-drop model="logo" accept="image/*" title="Upload a logo" hint="PNG or JPG, square works best">…preview…</x-ui.file-drop>
--}}
@props(['model', 'accept' => 'image/*', 'title' => 'Choose a file or drop it here', 'hint' => null, 'multiple' => false, 'cover' => false])
<div x-data="{ over: false, progress: null, chosen: '' }"
     x-on:dragenter.prevent="over = true" x-on:dragleave.prevent="over = $el.contains($event.relatedTarget)" x-on:drop="over = false"
     x-on:livewire-upload-start="progress = 0" x-on:livewire-upload-progress="progress = $event.detail.progress"
     x-on:livewire-upload-finish="progress = null" x-on:livewire-upload-error="progress = null; chosen = ''"
     :class="over ? 'border-brand-600 bg-brand-600/10' : 'border-gray-300 hover:border-brand-600/70'"
     {{ $attributes->class(['group relative isolate overflow-hidden rounded-xl border-2 border-dashed transition focus-within:border-brand-600 focus-within:ring-2 focus-within:ring-brand-600/30', 'p-4' => ! $cover]) }}>
    <div @class(['flex items-center gap-4' => ! $cover])>
        @if (! $slot->isEmpty())
            <div @class(['shrink-0' => ! $cover])>{{ $slot }}</div>
        @endif
        <div @class([
            'min-w-0 flex-1' => ! $cover,
            'absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-4 pt-10 pb-3 text-white' => $cover,
        ])>
            <p class="flex items-center gap-2 text-sm font-semibold {{ $cover ? 'text-white' : 'text-gray-900' }}">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white shadow-[0_6px_16px_-6px_var(--gym-orange)] transition group-hover:scale-105 motion-reduce:transition-none">
                    <x-ui.icon name="upload" class="size-4" />
                </span>
                <span class="truncate" x-text="chosen || @js($title)">{{ $title }}</span>
            </p>
            @if ($hint)
                <p class="mt-1 text-xs {{ $cover ? 'text-white/80' : 'text-gray-500' }}" x-show="progress === null">{{ $hint }}</p>
            @endif
            <div x-show="progress !== null" x-cloak class="mt-2 flex items-center gap-2 text-xs {{ $cover ? 'text-white/90' : 'text-gray-500' }}">
                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200/40"><span class="block h-full rounded-full bg-brand-600 transition-[width]" :style="`width: ${progress ?? 0}%`"></span></span>
                <span x-text="`${progress ?? 0}%`"></span>
            </div>
        </div>
    </div>
    <input wire:model="{{ $model }}" type="file" accept="{{ $accept }}" @if ($multiple) multiple @endif aria-label="{{ $title }}"
           x-on:change="chosen = $event.target.files.length > 1 ? `${$event.target.files.length} files chosen` : ($event.target.files[0]?.name ?? '')"
           class="absolute inset-0 z-10 size-full cursor-pointer opacity-0">
</div>
