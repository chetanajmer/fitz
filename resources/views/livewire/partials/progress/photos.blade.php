<div class="card-header"><h2 class="card-title">Progress photos</h2><span class="text-xs text-gray-500">Private: only the member and their gym's staff with AI access see them</span></div>
<form wire:submit="uploadPhoto" class="card-body grid gap-3 sm:grid-cols-3">
    <x-ui.field label="Photo" name="photo" class="sm:col-span-3"><input wire:model="photo" type="file" accept="image/jpeg,image/png,image/webp" class="input"></x-ui.field>
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
