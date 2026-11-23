@props(['message' => 'Nothing here yet.', 'icon' => 'inbox'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <x-ui.icon :name="$icon" class="size-6" />
    </div>
    <p class="mt-3 text-sm text-gray-500">{{ $message }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
