<div class="card-header"><h2 class="card-title">Progress photos</h2><span class="text-xs text-gray-500">Private: only the member and their gym's staff with AI access see them</span></div>
<form wire:submit="uploadPhoto" class="card-body grid gap-3 sm:grid-cols-3">
    <x-ui.field name="photo" class="sm:col-span-3">
        {{-- The input covers the whole area, so clicking and dropping a file both work. --}}
        <div x-data="{ file: null, url: null }" class="relative flex min-h-36 items-center justify-center gap-4 rounded-xl border-2 border-dashed border-gray-300 p-4 text-center transition focus-within:border-brand-600 hover:border-brand-600">
            <template x-if="$wire.photo && url"><img :src="url" alt="" class="h-28 w-20 rounded-lg object-cover"></template>
            <div class="text-sm">
                <x-ui.icon name="camera" class="mx-auto mb-1 size-7 text-brand-600" x-show="! $wire.photo" />
                <p class="font-semibold text-gray-900" x-text="$wire.photo && file ? file : 'Choose a photo or drop it here'">Choose a photo or drop it here</p>
                <p class="text-gray-500"><span wire:loading.remove wire:target="photo">JPG, PNG or WebP, up to 6 MB</span><span wire:loading wire:target="photo">Uploading…</span></p>
            </div>
            <input wire:model="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Photo" class="absolute inset-0 size-full cursor-pointer opacity-0"
                   x-on:change="if ($event.target.files[0]) { if (url) URL.revokeObjectURL(url); file = $event.target.files[0].name; url = URL.createObjectURL($event.target.files[0]) }">
        </div>
    </x-ui.field>
    <x-ui.field label="Pose" name="pose"><select wire:model="pose" class="input">@foreach (\App\Models\ProgressPhoto::POSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></x-ui.field>
    <x-ui.field label="Taken on" name="takenOn"><input wire:model="takenOn" type="date" max="{{ now()->toDateString() }}" class="input"></x-ui.field>
    <div class="flex items-end"><button type="submit" wire:loading.attr="disabled" wire:target="photo,uploadPhoto" class="btn-primary btn-sm"><x-ui.icon name="upload" class="size-4" /> Upload</button></div>
</form>
<div class="grid {{ $photoColumns ?? 'grid-cols-3' }} gap-3 p-5 pt-0">
    @forelse ($photos as $p)
        <div wire:key="ph-{{ $p->id }}" class="group relative overflow-hidden rounded-lg border border-gray-200">
            <img src="{{ $photoUrl($p->id) }}" alt="" loading="lazy" class="aspect-[3/4] w-full object-cover">
            <div class="flex items-center justify-between px-2 py-1 text-xs">
                <span>{{ $p->taken_on->format('d M Y') }} · {{ ucfirst($p->pose) }}</span>
                <button wire:click="deletePhoto({{ $p->id }})" wire:confirm="Delete this photo?" class="font-medium text-red-600 hover:underline">Delete</button>
            </div>
        </div>
    @empty
        <div class="col-span-full"><x-ui.empty message="No progress photos yet." icon="camera" /></div>
    @endforelse
</div>
