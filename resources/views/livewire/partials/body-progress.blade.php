@php
    $fields = \App\Models\MemberMeasurement::FIELDS;
@endphp
    <x-progress.transformation :data="$transformation" :photo-url="$photoUrl" />

    {{-- AI reports --}}
    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <h2 class="card-title">AI reports</h2>
            <div class="flex flex-wrap items-center gap-2">
                <button wire:click="requestReport('progress')" wire:loading.attr="disabled" class="btn-primary btn-sm" @disabled(! $status['features']['progress']['available'] || ! $status['has_consented'])>
                    <x-ui.icon name="sparkles" class="size-4" /> Progress report ({{ $status['features']['progress']['credits'] }})
                </button>
                @if ($status['features']['photo_comparison']['available'] && $photos->count() >= 2)
                    <select wire:model="beforePhotoId" class="input w-auto py-1 text-sm"><option value="">Before photo</option>@foreach ($photos as $p)<option value="{{ $p->id }}">{{ $p->taken_on->format('d M Y') }} · {{ $p->pose }}</option>@endforeach</select>
                    <select wire:model="afterPhotoId" class="input w-auto py-1 text-sm"><option value="">After photo</option>@foreach ($photos as $p)<option value="{{ $p->id }}">{{ $p->taken_on->format('d M Y') }} · {{ $p->pose }}</option>@endforeach</select>
                    <button wire:click="requestReport('photo_comparison')" wire:loading.attr="disabled" class="btn-secondary btn-sm" @disabled(! $status['has_consented'])>
                        <x-ui.icon name="camera" class="size-4" /> Compare ({{ $status['features']['photo_comparison']['credits'] }})
                    </button>
                @endif
            </div>
        </div>
        @foreach (['report', 'beforePhotoId', 'afterPhotoId'] as $field)
            @error($field)<p class="input-error px-5 pt-3">{{ $message }}</p>@enderror
        @endforeach
        <div class="divide-y divide-gray-100" @if ($reports->contains('status', 'pending')) wire:poll.5s @endif>
            @forelse ($reports as $report)
                <div wire:key="air-{{ $report->id }}" class="card-body">
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold">{{ \App\Models\AiReport::TYPES[$report->type] }}</span>
                        <x-ui.badge :color="['completed' => 'green', 'failed' => 'red'][$report->status] ?? 'amber'">{{ $report->status === 'pending' ? 'Preparing…' : ucfirst($report->status) }}</x-ui.badge>
                        <span class="text-gray-500">{{ $report->created_at->format('d M Y, h:i A') }} · requested by {{ $report->requested_by === 'staff' ? ($report->requester?->name ?? 'gym') : $report->requested_by }}</span>
                    </div>
                    @if ($report->status === 'completed')
                        <x-progress.report :report="$report" :photo-url="$photoUrl" />
                        <p class="hint mt-2">{{ \App\Models\AiReport::DISCLAIMER }}</p>
                    @elseif ($report->status === 'failed')
                        <p class="text-sm text-gray-500">The AI could not write this report. No credits were used.</p>
                    @endif
                </div>
            @empty
                <div class="card-body"><x-ui.empty message="No AI reports yet." icon="sparkles" /></div>
            @endforelse
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Measurements --}}
        <div class="card">
            <div class="card-header"><h2 class="card-title">Body measurements</h2></div>
            <form wire:submit="saveMeasurement" class="card-body grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-ui.field label="Date" name="measurement.measured_on" class="col-span-2"><input wire:model="measurement.measured_on" type="date" max="{{ now()->toDateString() }}" class="input"></x-ui.field>
                @foreach ($fields as $key => $label)
                    <x-ui.field :label="$label" :name="'measurement.'.$key" wire:key="mf-{{ $key }}"><input wire:model="measurement.{{ $key }}" type="number" step="0.1" class="input"></x-ui.field>
                @endforeach
                <div class="col-span-2 flex items-end sm:col-span-4"><button type="submit" class="btn-primary btn-sm">Save measurements</button></div>
            </form>
            <div class="max-h-72 overflow-auto">
                <table class="table">
                    <thead><tr><th>Date</th>@foreach ($fields as $label)<th>{{ \Illuminate\Support\Str::before($label, ' (') }}</th>@endforeach<th></th></tr></thead>
                    <tbody>
                        @forelse ($measurements as $m)
                            <tr wire:key="m-{{ $m->id }}">
                                <td>{{ $m->measured_on->format('d M Y') }}</td>
                                @foreach (array_keys($fields) as $key)<td>{{ $m->{$key} ?? '—' }}</td>@endforeach
                                <td class="text-right"><button wire:click="deleteMeasurement({{ $m->id }})" wire:confirm="Delete these measurements?" class="text-xs font-medium text-red-600 hover:underline">Delete</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($fields) + 2 }}"><x-ui.empty message="No measurements yet." icon="scale" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Progress photos --}}
        <div class="card">
            <div class="card-header"><h2 class="card-title">Progress photos</h2><span class="text-xs text-gray-500">Private: only the member and their gym's staff with AI access see them</span></div>
            <form wire:submit="uploadPhoto" class="card-body grid gap-3 sm:grid-cols-3">
                <x-ui.field label="Photo" name="photo" class="sm:col-span-3"><input wire:model="photo" type="file" accept="image/jpeg,image/png,image/webp" class="input"></x-ui.field>
                <x-ui.field label="Pose" name="pose"><select wire:model="pose" class="input">@foreach (\App\Models\ProgressPhoto::POSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Taken on" name="takenOn"><input wire:model="takenOn" type="date" max="{{ now()->toDateString() }}" class="input"></x-ui.field>
                <div class="flex items-end"><button type="submit" wire:loading.attr="disabled" wire:target="photo,uploadPhoto" class="btn-primary btn-sm"><x-ui.icon name="upload" class="size-4" /> Upload</button></div>
            </form>
            <div class="grid grid-cols-3 gap-3 p-5 pt-0">
                @forelse ($photos as $p)
                    <div wire:key="ph-{{ $p->id }}" class="group relative overflow-hidden rounded-lg border border-gray-200">
                        <img src="{{ $photoUrl($p->id) }}" alt="" loading="lazy" class="aspect-[3/4] w-full object-cover">
                        <div class="flex items-center justify-between px-2 py-1 text-xs">
                            <span>{{ $p->taken_on->format('d M Y') }} · {{ ucfirst($p->pose) }}</span>
                            <button wire:click="deletePhoto({{ $p->id }})" wire:confirm="Delete this photo?" class="font-medium text-red-600 hover:underline">Delete</button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3"><x-ui.empty message="No progress photos yet." icon="camera" /></div>
                @endforelse
            </div>
        </div>
    </div>
