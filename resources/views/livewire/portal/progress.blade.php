<div class="space-y-6">
    <x-ui.page-header title="My progress" subtitle="Your photos, measurements and AI coach reports." />

    <div class="card card-body space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="card-title">AI coach</h2>
            <x-ui.badge :color="$status['available'] ? 'green' : 'gray'">{{ $status['available'] ? 'Available' : 'Not available at the moment' }}</x-ui.badge>
        </div>
        @if ($status['has_consented'])
            <p class="text-sm text-gray-500">{{ $status['credits']['remaining'] }} of {{ $status['credits']['limit'] }} credits left this month. You accepted the AI terms on {{ \Illuminate\Support\Carbon::parse($status['consented_at'])->format('d M Y') }}.</p>
            <button type="button" wire:click="setConsent(false)" wire:confirm="Withdraw your consent? Reports being prepared are cancelled." class="self-start text-sm text-gray-500 hover:underline">Withdraw consent</button>
        @elseif ($status['available'])
            <p class="text-sm text-gray-500">{{ $status['consent_text'] }}</p>
            <button type="button" wire:click="setConsent(true)" class="btn-primary btn-sm self-start">I agree</button>
        @else
            <p class="text-sm text-gray-500">Ask your gym about AI coach. You can still record photos and measurements.</p>
        @endif
    </div>

    @include('livewire.partials.body-progress')
</div>
