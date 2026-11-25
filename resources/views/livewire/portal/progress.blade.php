<div class="space-y-6">
    <x-ui.page-header title="My progress" subtitle="Your photos, measurements and AI coach reports." />

    <div class="card card-body space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="card-title">AI coach</h2>
            <x-ui.badge :color="$status['available'] ? 'green' : 'gray'">{{ $status['available'] ? 'Available' : 'Not available at the moment' }}</x-ui.badge>
        </div>
        @if ($status['has_consented'])
            <p class="text-sm text-gray-500">{{ $status['credits']['remaining'] }} of {{ $status['credits']['limit'] }} credits left this month. You accepted the AI terms on {{ \Illuminate\Support\Carbon::parse($status['consented_at'])->format('d M Y') }}.</p>
            <button type="button" wire:click="setConsent(false)" wire:confirm="Withdraw your consent? All your AI reports and progress photos are deleted and cannot be restored. Your measurements stay, and credits you already used are not refunded." class="self-start text-sm text-gray-500 hover:underline">Withdraw consent</button>
        @elseif ($status['available'])
            <p class="text-sm text-gray-500">{{ $status['consent_text'] }}</p>
            <button type="button" wire:click="setConsent(true)" class="btn-primary btn-sm self-start">I agree</button>
        @else
            <p class="text-sm text-gray-500">Ask your gym about AI coach. You can still record photos and measurements.</p>
        @endif
    </div>

    <div class="card">
        @include('livewire.partials.progress.photos', ['photoColumns' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-6'])
        @if ($status['features']['photo_comparison']['available'])
            <div class="card-body space-y-3 border-t border-gray-100">
                <p class="text-sm text-gray-500">Two photos are all you need. AI coach compares them and describes what changed.</p>
                @if ($photos->count() < 2)
                    <p class="hint">Add two photos to compare them.</p>
                @else
                    <div class="flex flex-wrap items-center gap-2">
                        @include('livewire.partials.progress.compare-controls')
                    </div>
                    @unless ($status['has_consented'])
                        <p class="hint">Accept the AI terms above first.</p>
                    @endunless
                @endif
            </div>
        @endif
    </div>

    @include('livewire.partials.progress.reports', ['compareInReports' => false])

    <x-progress.transformation :data="$transformation" :photo-url="$photoUrl" />

    <details wire:ignore.self class="card">
        <summary class="card-header cursor-pointer"><h2 class="card-title">Add measurements (optional)</h2><x-ui.icon name="plus" class="size-4" /></summary>
        @include('livewire.partials.progress.measurements')
    </details>
</div>
