@php
    $fields = \App\Models\MemberMeasurement::FIELDS;
@endphp
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
