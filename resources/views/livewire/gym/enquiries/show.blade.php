<div>
    <x-ui.page-header :title="$e->name" :subtitle="'Enquiry #'.$e->id.' · received '.$e->created_at->format('d M Y, h:i A').' via '.$e->source->label()">
        <x-slot:actions>
            <a href="{{ route('gym.enquiries.index') }}" class="btn-secondary">Back</a>
            @unless ($e->isConverted())
                <button wire:click="openEdit" class="btn-secondary">Edit</button>
                @can('members.manage')
                    <button wire:click="openConvert" class="btn-success">Convert to member</button>
                @endcan
            @endunless
        </x-slot:actions>
    </x-ui.page-header>

    @if ($e->isConverted())
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            <span>Converted on {{ $e->converted_at?->format('d M Y') }}. This enquiry's history is preserved below.</span>
            @if ($e->convertedMember)
                <a href="{{ route('gym.members.show', $e->convertedMember) }}" class="btn-success btn-sm">View member {{ $e->convertedMember->member_code }}</a>
            @endif
        </div>
    @elseif ($duplicates->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            A member with this phone already exists:
            @foreach ($duplicates as $d)<a href="{{ route('gym.members.show', $d->id) }}" class="font-medium underline">{{ $d->name }} ({{ $d->member_code }})</a>@if(! $loop->last), @endif @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2><x-ui.badge :color="$e->status->color()">{{ $e->status->label() }}</x-ui.badge></div>
                <dl class="card-body space-y-3 text-sm">
                    <div><dt class="text-xs text-gray-500">Phone</dt><dd class="font-medium"><a href="tel:{{ $e->phone }}" class="text-indigo-600">{{ $e->phone }}</a> · <a href="https://wa.me/{{ preg_replace('/\D/', '', $e->phone) }}" target="_blank" class="text-emerald-600">WhatsApp</a></dd></div>
                    <div><dt class="text-xs text-gray-500">Email</dt><dd class="font-medium">{{ $e->email ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Fitness goal</dt><dd class="font-medium">{{ $e->fitness_goal ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Interested plan</dt><dd class="font-medium">{{ $e->interestedPlan?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Follow-up</dt><dd class="font-medium">{{ $e->follow_up_date?->format('d M Y') ?? '—' }}</dd></div>
                    @if ($e->notes)<div><dt class="text-xs text-gray-500">Notes</dt><dd class="whitespace-pre-line text-gray-700">{{ $e->notes }}</dd></div>@endif
                </dl>
            </div>

            @unless ($e->isConverted())
                <form wire:submit="updateStatus" class="card card-body space-y-4">
                    <h2 class="card-title">Update follow-up</h2>
                    <x-ui.field label="Status" name="newStatus">
                        <select wire:model="newStatus" class="input">@foreach (\App\Enums\EnquiryStatus::manualOptions() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                    </x-ui.field>
                    <x-ui.field label="Next follow-up date" name="followUpDate"><input wire:model="followUpDate" type="date" class="input"></x-ui.field>
                    <x-ui.field label="Note" name="note"><textarea wire:model="note" rows="2" class="input" placeholder="What happened on this call / visit?"></textarea></x-ui.field>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary flex-1">Save update</button>
                        <button type="button" wire:click="addNote" class="btn-secondary">Note only</button>
                    </div>
                </form>
            @endunless
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Activity history</h2></div>
            <ol class="card-body space-y-5">
                @forelse ($e->activities as $a)
                    <li wire:key="act-{{ $a->id }}" class="relative flex gap-3">
                        <div class="mt-1 size-2.5 shrink-0 rounded-full {{ $a->type === 'converted' ? 'bg-emerald-500' : ($a->type === 'note' ? 'bg-gray-400' : 'bg-indigo-500') }}"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-900">
                                @if ($a->type === 'status_changed' && $a->from_status !== $a->to_status)
                                    Status <strong>{{ \App\Enums\EnquiryStatus::tryFrom($a->from_status)?->label() }}</strong> <x-ui.icon name="arrow-right" class="inline size-3.5 text-gray-400" /> <strong>{{ \App\Enums\EnquiryStatus::tryFrom($a->to_status)?->label() }}</strong>
                                @elseif ($a->type === 'note')
                                    Note added
                                @elseif ($a->type === 'converted')
                                    <strong class="text-emerald-700">Converted to member</strong>
                                @elseif ($a->type === 'created')
                                    Enquiry received
                                @else
                                    Follow-up updated
                                @endif
                            </p>
                            @if ($a->note)<p class="mt-1 rounded-lg bg-gray-50 p-2 text-sm whitespace-pre-line text-gray-700">{{ $a->note }}</p>@endif
                            <p class="mt-1 text-xs text-gray-500">{{ $a->created_at->format('d M Y, h:i A') }} · {{ $a->user?->name ?? 'Public form' }}</p>
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty message="No activity yet." /></li>
                @endforelse
            </ol>
        </div>
    </div>

    <x-ui.modal wire-model="showEdit" title="Edit enquiry" max-width="xl">
        <form wire:submit="saveEdit" class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Name" name="edit.name" required><input wire:model="edit.name" class="input"></x-ui.field>
            <x-ui.field label="Phone" name="edit.phone" required><input wire:model="edit.phone" class="input"></x-ui.field>
            <x-ui.field label="Email" name="edit.email"><input wire:model="edit.email" type="email" class="input"></x-ui.field>
            <x-ui.field label="Fitness goal" name="edit.fitness_goal"><input wire:model="edit.fitness_goal" class="input"></x-ui.field>
            <x-ui.field label="Interested plan" name="edit.interested_plan_id">
                <select wire:model="edit.interested_plan_id" class="input"><option value="">—</option>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Source" name="edit.source">
                <select wire:model="edit.source" class="input">@foreach (\App\Enums\EnquirySource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Notes" name="edit.notes" class="sm:col-span-2"><textarea wire:model="edit.notes" rows="2" class="input"></textarea></x-ui.field>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" wire:click="$set('showEdit', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal wire-model="showConvert" title="Convert to member" max-width="2xl">
        <form wire:submit="convertToMember" class="space-y-5">
            @error('enquiry') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
            @error('start_date') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
            <p class="text-sm text-gray-600">Creates a member profile for <strong>{{ $e->name }}</strong> ({{ $e->phone }}). The enquiry and its history are kept and linked.</p>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-ui.field label="Gender" name="convert.gender">
                    <select wire:model="convert.gender" class="input"><option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select>
                </x-ui.field>
                <x-ui.field label="Date of birth" name="convert.date_of_birth"><input wire:model="convert.date_of_birth" type="date" class="input"></x-ui.field>
                <x-ui.field label="Joining date" name="convert.joining_date" required><input wire:model="convert.joining_date" type="date" class="input"></x-ui.field>
                @if ($canAssignTrainer)
                    <x-ui.field label="Trainer" name="convert.trainer_id">
                        <select wire:model="convert.trainer_id" class="input"><option value="">None</option>@foreach ($trainers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                    </x-ui.field>
                @endif
            </div>
            @if (! $canAssignTrainer)<p class="hint">The new member will be assigned to you.</p>@endif
            @if ($canAssignPlan)
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="mb-3 text-sm font-medium">Membership (optional)</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field label="Plan" name="convert.plan_id" class="sm:col-span-3">
                        <select wire:model.live="convert.plan_id" class="input"><option value="">Assign later</option>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->durationLabel() }} — {{ money($p->price, false) }}</option>@endforeach</select>
                    </x-ui.field>
                    @if ($convert['plan_id'] ?? false)
                        <x-ui.field label="Start date" name="convert.start_date"><input wire:model="convert.start_date" type="date" class="input"></x-ui.field>
                        <x-ui.field label="Discount (₹)" name="convert.discount"><input wire:model="convert.discount" type="number" min="0" step="0.01" class="input"></x-ui.field>
                        @if ($canPay)<label class="flex items-end gap-2 pb-2 text-sm font-medium"><input wire:model.live="convert.pay_now" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Record payment</label>@endif
                        @if ($canPay && ($convert['pay_now'] ?? false))
                            <x-ui.field label="Amount (₹)" name="convert.amount"><input wire:model="convert.amount" type="number" min="0" step="0.01" class="input"></x-ui.field>
                            <x-ui.field label="Method" name="convert.method"><select wire:model="convert.method" class="input">@foreach (\App\Enums\PaymentMethod::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-ui.field>
                            <x-ui.field label="Reference" name="convert.reference"><input wire:model="convert.reference" class="input"></x-ui.field>
                        @endif
                    @endif
                </div>
            </div>
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showConvert', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-success" wire:loading.attr="disabled">Convert</button>
            </div>
        </form>
    </x-ui.modal>
</div>
