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
    @foreach (($compareInReports ?? true) ? ['report', 'beforePhotoId', 'afterPhotoId'] : ['report'] as $field)
        @error($field)<p class="input-error px-5 pt-3">{{ $message }}</p>@enderror
    @endforeach
    <div class="divide-y divide-gray-100" @if ($reports->contains('status', 'pending')) wire:poll.5s @endif>
        @php($newest = $reports->firstWhere('status', \App\Models\AiReport::COMPLETED)?->id)
        @forelse ($reports as $report)
            @php($requester = ['member' => null, 'staff' => 'requested by '.($report->requester?->name ?? 'your gym'), 'auto' => 'monthly report'][$report->requested_by] ?? null)
            @if ($report->status === 'completed')
                <details wire:key="air-{{ $report->id }}-done" wire:ignore.self class="group" @if ($report->id === $newest) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-5 py-4 text-sm hover:bg-gray-50 [&::-webkit-details-marker]:hidden">
                        <x-ui.icon name="arrow-right" class="size-4 shrink-0 text-gray-400 transition-transform group-open:rotate-90 motion-reduce:transition-none" />
                        <span class="font-semibold text-gray-900">{{ \App\Models\AiReport::TYPES[$report->type] }}</span>
                        @isset($report->result['change_level'])<span class="text-gray-700 group-open:hidden">{{ \App\Models\AiReport::CHANGE_LEVELS[$report->result['change_level']] ?? '' }}</span>@endisset
                        <span class="ml-auto text-xs text-gray-500">{{ $report->created_at->format('d M Y') }}@if ($requester), {{ $requester }}@endif</span>
                    </summary>
                    <div class="px-5 pt-2 pb-6"><x-progress.report :report="$report" :photo-url="$photoUrl" :highlight="$highlight($report)" :share-name="$shareName ?? null" /></div>
                </details>
            @else
                <div wire:key="air-{{ $report->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4 text-sm">
                    <span class="font-semibold text-gray-900">{{ \App\Models\AiReport::TYPES[$report->type] }}</span>
                    <x-ui.badge :color="$report->status === 'failed' ? 'red' : 'amber'">{{ $report->status === 'pending' ? 'Preparing…' : ucfirst($report->status) }}</x-ui.badge>
                    <span class="ml-auto text-xs text-gray-500">{{ $report->created_at->format('d M Y') }}@if ($requester), {{ $requester }}@endif</span>
                    @if ($report->status === 'failed')
                        <p class="w-full text-gray-500">The AI could not write this report. No credits were used.</p>
                    @endif
                </div>
            @endif
        @empty
            <div class="card-body"><x-ui.empty message="No AI reports yet." icon="sparkles" /></div>
        @endforelse
    </div>
    @if ($newest)
        <p class="hint border-t border-gray-100 px-5 py-3">{{ \App\Models\AiReport::DISCLAIMER }}</p>
    @endif
</div>
