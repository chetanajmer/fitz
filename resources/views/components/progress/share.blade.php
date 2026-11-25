{{-- Share button and preview of the poster image. Drawn in the member's browser: nothing is uploaded. --}}
@props(['report', 'highlight', 'photoUrl', 'name', 'step' => null])
@php
    $card = [
        'headline' => $highlight['headline'],
        'step' => $step,
        'period' => $highlight['period'],
        'stats' => $highlight['stats'],
        'photos' => $report->before_photo_id && $report->after_photo_id ? [$photoUrl($report->before_photo_id), $photoUrl($report->after_photo_id)] : null,
        'gym' => (string) current_gym()?->name,
        'name' => $name,
    ];
@endphp
<div wire:ignore wire:key="share-{{ $report->id }}-{{ md5(json_encode($card)) }}" x-data="shareCard(@js($card))">
    <button type="button" x-on:click="open()" class="btn-primary"><x-ui.icon name="external" class="size-4" /> Share</button>

    <dialog x-ref="dialog" x-on:click.self="$el.close()" aria-label="Share your progress"
            class="fixed inset-0 m-auto max-h-[95dvh] w-[calc(100%-2rem)] max-w-sm overflow-y-auto rounded-2xl bg-surface p-5 text-left text-sm text-gray-700 shadow-xl backdrop:bg-black/70">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Share your progress</h3>
            <button type="button" x-on:click="$refs.dialog.close()" class="rounded p-1 text-gray-400 hover:text-gray-700" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button>
        </div>

        <canvas x-ref="canvas" role="img" aria-label="Preview of the image" class="mx-auto max-h-[45dvh] max-w-full rounded-lg"></canvas>
        <p x-show="failed" x-cloak class="input-error text-center">The image could not be made. Close this and try again.</p>

        <div class="mt-4 space-y-3">
            <div class="grid grid-cols-2 gap-2">
                @foreach (['post' => 'Post', 'story' => 'Story'] as $value => $label)
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-200 py-2 has-checked:border-brand-600"><input type="radio" value="{{ $value }}" x-model="format" x-on:change="draw()" name="format-{{ $report->id }}" class="text-brand-600 focus:ring-brand-500"> {{ $label }}</label>
                @endforeach
            </div>
            @if ($card['photos'])
                <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" x-model="photos" x-on:change="draw()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"> Show my photos</label>
            @endif
            @if ($card['stats'])
                <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" x-model="numbers" x-on:change="draw()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"> Show my numbers</label>
            @endif
            <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" x-model="name" x-on:change="draw()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"> Show my first name</label>
        </div>

        <div class="mt-5 flex gap-2">
            <button type="button" x-show="canShare" x-on:click="share()" :disabled="!ready" class="btn-primary flex-1">Share</button>
            <button type="button" x-on:click="save()" :disabled="!ready" class="btn-secondary flex-1">Save image</button>
        </div>
        <p class="hint">The image is made on your device. Only people you send it to can see it.</p>
    </dialog>
</div>
