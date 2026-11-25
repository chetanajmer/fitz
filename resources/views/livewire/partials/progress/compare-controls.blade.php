{{-- Before/after pickers and the Compare button: needs at least two photos. --}}
<select wire:model="beforePhotoId" class="input w-auto py-1 text-sm"><option value="">Before photo</option>@foreach ($photos as $p)<option value="{{ $p->id }}">{{ $p->taken_on->format('d M Y') }} · {{ $p->pose }}</option>@endforeach</select>
<select wire:model="afterPhotoId" class="input w-auto py-1 text-sm"><option value="">After photo</option>@foreach ($photos as $p)<option value="{{ $p->id }}">{{ $p->taken_on->format('d M Y') }} · {{ $p->pose }}</option>@endforeach</select>
<button wire:click="requestReport('photo_comparison')" wire:loading.attr="disabled" class="btn-secondary btn-sm" @disabled(! $status['has_consented'])>
    <x-ui.icon name="camera" class="size-4" /> Compare ({{ $status['features']['photo_comparison']['credits'] }})
</button>
