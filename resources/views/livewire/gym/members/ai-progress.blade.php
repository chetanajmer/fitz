@php
    $credits = $status['credits'];
@endphp
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="AI coach" :value="$status['available'] ? 'Available' : 'Not available'" icon="sparkles" :color="$status['available'] ? 'green' : 'gray'"
                   :hint="$status['available'] ? 'Gym switched on, member eligible' : 'Check AI Coach settings and the membership'" />
        <x-ui.stat label="Consent" :value="$status['has_consented'] ? 'Given' : 'Not given'" icon="shield" :color="$status['has_consented'] ? 'green' : 'amber'"
                   :hint="$status['has_consented'] ? 'On '.\Illuminate\Support\Carbon::parse($status['consented_at'])->format('d M Y') : 'The member accepts it in the app'" />
        <x-ui.stat label="Credits this month" :value="$credits['used'].' / '.$credits['limit']" icon="bolt" :color="$credits['remaining'] ? 'brand' : 'red'" :hint="'Resets '.\Illuminate\Support\Carbon::parse($credits['resets_on'])->format('d M')" />
        @can('ai.manage')
            <div class="card card-body flex flex-col justify-center gap-2">
                <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Hand-picked for AI</p>
                <button wire:click="toggleAccess" class="{{ $member->ai_access ? 'btn-secondary' : 'btn-primary' }} btn-sm self-start">{{ $member->ai_access ? 'Remove AI access' : 'Give AI access' }}</button>
                <p class="hint">Used when AI Coach is set to "Hand-picked members".</p>
            </div>
        @endcan
    </div>

    @include('livewire.partials.body-progress')
</div>
