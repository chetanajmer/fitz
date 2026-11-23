<div>
    <x-ui.page-header title="Enquiries" subtitle="Leads from walk-ins, calls and your QR registration link.">
        <x-slot:actions>
            <a href="{{ route('gym.settings') }}#qr" class="btn-secondary"><x-ui.icon name="qr" class="size-4" /> QR link</a>
            <button wire:click="create" class="btn-primary"><x-ui.icon name="plus" class="size-4" /> New enquiry</button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Status pills --}}
    <div class="mb-4 flex flex-wrap gap-2">
        <button wire:click="$set('status', '')" class="rounded-full px-3 py-1 text-xs font-medium {{ $status === '' ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200' }}">All · {{ $counts->sum() }}</button>
        @foreach (\App\Enums\EnquiryStatus::cases() as $s)
            <button wire:key="pill-{{ $s->value }}" wire:click="$set('status', '{{ $s->value }}')" class="rounded-full px-3 py-1 text-xs font-medium {{ $status === $s->value ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200' }}">{{ $s->label() }} · {{ $counts[$s->value] ?? 0 }}</button>
        @endforeach
        @if ($dueCount)
            <button wire:click="$set('followUp', '{{ $followUp === 'due' ? '' : 'due' }}')" class="rounded-full px-3 py-1 text-xs font-medium {{ $followUp === 'due' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' }}">⏰ {{ $dueCount }} follow-ups due</button>
        @endif
    </div>

    <div class="card">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, phone or email…" class="input pl-9">
            </div>
            <select wire:model.live="source" class="input md:w-44">
                <option value="">All sources</option>
                @foreach (\App\Enums\EnquirySource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
            <select wire:model.live="followUp" class="input md:w-44">
                <option value="">Any follow-up</option>
                <option value="due">Due / overdue</option>
                <option value="upcoming">Upcoming</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Phone</th><th>Goal / plan</th><th>Source</th><th>Follow-up</th><th>Status</th><th>Received</th></tr></thead>
                <tbody>
                    @forelse ($enquiries as $e)
                        <tr wire:key="enq-{{ $e->id }}" class="cursor-pointer" onclick="window.location='{{ route('gym.enquiries.show', $e) }}'">
                            <td class="font-medium text-gray-900">{{ $e->name }}</td>
                            <td>{{ $e->phone }}</td>
                            <td><span class="block">{{ $e->fitness_goal ?: '—' }}</span><span class="text-xs text-gray-500">{{ $e->interestedPlan?->name }}</span></td>
                            <td>{{ $e->source->label() }}</td>
                            <td>
                                @if ($e->follow_up_date && ! $e->isConverted())
                                    <span class="{{ $e->follow_up_date->lte(today()) ? 'font-medium text-amber-700' : '' }}">{{ $e->follow_up_date->format('d M Y') }}</span>
                                @else — @endif
                            </td>
                            <td><x-ui.badge :color="$e->status->color()">{{ $e->status->label() }}</x-ui.badge></td>
                            <td class="text-gray-500">{{ $e->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty message="No enquiries found." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($enquiries->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $enquiries->links() }}</div>@endif
    </div>

    <x-ui.modal wire-model="showForm" title="New enquiry" max-width="xl">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Name" name="form.name" required><input wire:model="form.name" class="input"></x-ui.field>
            <x-ui.field label="Phone" name="form.phone" required><input wire:model="form.phone" class="input"></x-ui.field>
            <x-ui.field label="Email" name="form.email"><input wire:model="form.email" type="email" class="input"></x-ui.field>
            <x-ui.field label="Fitness goal" name="form.fitness_goal"><input wire:model="form.fitness_goal" class="input"></x-ui.field>
            <x-ui.field label="Interested plan" name="form.interested_plan_id">
                <select wire:model="form.interested_plan_id" class="input"><option value="">—</option>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Source" name="form.source" required>
                <select wire:model="form.source" class="input">@foreach (\App\Enums\EnquirySource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Follow-up date" name="form.follow_up_date"><input wire:model="form.follow_up_date" type="date" class="input"></x-ui.field>
            <x-ui.field label="Notes" name="form.notes" class="sm:col-span-2"><textarea wire:model="form.notes" rows="2" class="input"></textarea></x-ui.field>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save enquiry</button>
            </div>
        </form>
    </x-ui.modal>
</div>
