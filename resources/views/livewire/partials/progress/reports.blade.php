<div class="card">
    <div class="card-header flex flex-wrap items-center justify-between gap-3">
        <h2 class="card-title">AI reports</h2>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="requestReport('progress')" wire:loading.attr="disabled" class="btn-primary btn-sm" @disabled(! $status['features']['progress']['available'] || ! $status['has_consented'])>
                <x-ui.icon name="sparkles" class="size-4" /> Progress report ({{ $status['features']['progress']['credits'] }})
            </button>
            @if ($status['features']['photo_comparison']['available'] && $photos->count() >= 2 && ($compareInReports ?? true))
                @include('livewire.partials.progress.compare-controls')
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
