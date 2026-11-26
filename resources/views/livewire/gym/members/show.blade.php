<div>
    {{-- Header --}}
    <div class="card card-body mb-6 flex flex-col gap-5 md:flex-row md:items-center">
        <img src="{{ $m->photoUrl() }}" alt="" class="size-20 rounded-full object-cover ring-4 ring-indigo-50">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold text-gray-900">{{ $m->name }}</h1>
                <x-ui.badge :color="$m->status->color()">{{ $m->status->label() }}</x-ui.badge>
                <x-ui.badge :color="['active' => 'green', 'expired' => 'red', 'upcoming' => 'blue'][$state] ?? 'gray'">
                    {{ ['active' => 'Membership active', 'expired' => 'Membership expired', 'upcoming' => 'Starts soon', 'none' => 'No membership'][$state] }}
                </x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ $m->member_code }} · {{ $m->phone }} @if($m->email) · {{ $m->email }} @endif</p>
            <p class="mt-1 text-sm text-gray-500">
                Joined {{ $m->joining_date->format('d M Y') }}
                @if ($latest) · Valid till <strong class="text-gray-700">{{ $latest->end_date->format('d M Y') }}</strong> @endif
                @if ($pending > 0) · <span class="font-medium text-amber-700">Due {{ money($pending) }}</span> @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($todayAttendance && ! $todayAttendance->check_out_at)
                <button wire:click="checkOut" class="btn-secondary"><x-ui.icon name="clock" class="size-4" /> Check out</button>
            @elseif (! $todayAttendance)
                <button wire:click="checkIn" class="btn-success"><x-ui.icon name="check" class="size-4" /> Check in</button>
            @else
                <x-ui.badge color="green" class="self-center">Visited today</x-ui.badge>
            @endif
            @if ($canManage)
                @if ($canMembership)<button wire:click="openPlanModal('{{ $latest ? 'renew' : 'assign' }}')" class="btn-primary">{{ $latest ? 'Renew' : 'Assign plan' }}</button>@endif
                @if ($canPay && $openMemberships->isNotEmpty())
                    <button wire:click="openPaymentModal" class="btn-secondary"><x-ui.icon name="cash" class="size-4" /> Collect</button>
                @endif
                <a href="{{ route('gym.members.edit', $m) }}" class="btn-secondary">Edit</a>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
        @foreach (['overview' => 'Overview', 'memberships' => 'Memberships', 'payments' => 'Payments', 'attendance' => 'Attendance', 'progress' => 'Progress', 'plans' => 'Diet & Workout'] + (auth()->user()->can('useAi', $m) ? ['ai' => 'AI Progress'] : []) as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="tab {{ $tab === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Current membership</h2></div>
                    <div class="card-body">
                        @if ($current)
                            <div class="grid gap-4 sm:grid-cols-4">
                                <div><p class="text-xs text-gray-500">Plan</p><p class="font-medium">{{ $current->plan_name }}</p></div>
                                <div><p class="text-xs text-gray-500">Period</p><p class="font-medium">{{ $current->start_date->format('d M') }} – {{ $current->end_date->format('d M Y') }}</p></div>
                                <div><p class="text-xs text-gray-500">Days left</p><p class="font-medium">{{ $current->daysRemaining() }}</p>@if ($frozen = $current->freezeOn())<p class="text-xs font-medium text-sky-700">Frozen until {{ $frozen->end_date->format('d M') }}</p>@endif</div>
                                <div><p class="text-xs text-gray-500">Balance</p><p class="font-medium {{ $current->balance() > 0 ? 'text-amber-700' : 'text-emerald-600' }}">{{ $current->balance() > 0 ? money($current->balance()) : 'Paid' }}</p></div>
                            </div>
                        @else
                            <x-ui.empty :message="$state === 'upcoming' ? 'Next membership starts on '.$latest->start_date->format('d M Y').'.' : 'No active membership.'" icon="card">
                                @if ($canMembership)
                                    <button wire:click="openPlanModal('{{ $latest ? 'renew' : 'assign' }}')" class="btn-primary btn-sm">{{ $latest ? 'Renew membership' : 'Assign a plan' }}</button>
                                @endif
                            </x-ui.empty>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">Personal & contact</h2></div>
                    <dl class="card-body grid gap-4 text-sm sm:grid-cols-3">
                        @foreach ([
                            'Gender' => $m->gender ? ucfirst($m->gender) : null,
                            'Date of birth' => $m->date_of_birth?->format('d M Y').($m->date_of_birth ? ' ('.$m->date_of_birth->age.' yrs)' : ''),
                            'Blood group' => $m->blood_group,
                            'Height' => $m->height_cm ? (float) $m->height_cm.' cm' : null,
                            'Address' => trim($m->address.($m->city ? ', '.$m->city : ''), ', '),
                            'Fitness goal' => $m->fitness_goal,
                            'Emergency contact' => $m->emergency_contact_name ? $m->emergency_contact_name.($m->emergency_contact_relation ? ' ('.$m->emergency_contact_relation.')' : '').' · '.$m->emergency_contact_phone : null,
                        ] as $label => $value)
                            <div><dt class="text-xs text-gray-500">{{ $label }}</dt><dd class="mt-0.5 font-medium text-gray-800">{{ $value ?: '—' }}</dd></div>
                        @endforeach
                        @if ($m->medical_notes)
                            <div class="sm:col-span-3"><dt class="text-xs text-gray-500">Medical notes</dt><dd class="mt-0.5 rounded-lg bg-red-50 p-2 text-red-800">{{ $m->medical_notes }}</dd></div>
                        @endif
                        @if ($m->notes)
                            <div class="sm:col-span-3"><dt class="text-xs text-gray-500">Notes</dt><dd class="mt-0.5 whitespace-pre-line text-gray-700">{{ $m->notes }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card card-body">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 items-center justify-center rounded-xl bg-gray-900 text-orange-400"><x-ui.icon name="fire" class="size-6" /></div>
                        <div>
                            <p class="text-lg font-bold">{{ $streak['current_streak'] }} day streak</p>
                            <p class="text-xs text-gray-500">{{ $streak['visits_this_month'] }} visits this month · best {{ $streak['longest_streak'] }}</p>
                        </div>
                    </div>
                    @if ($streak['at_risk'])<p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">Streak breaks if they skip today.</p>@endif
                    <x-streak.week :week="$streak['week']" size="sm" class="mt-4" />
                    @if ($streak['next_milestone'])<p class="mt-3 text-xs text-gray-500">{{ $streak['next_milestone']['remaining'] }} more visit days to the {{ $streak['next_milestone']['target'] }}-day milestone.</p>@endif
                </div>
                <div class="card card-body text-center">
                    <p class="text-xs font-medium text-gray-500 uppercase">Attendance QR</p>
                    <img src="{{ route('gym.members.qr', $m) }}" alt="Member QR code" class="mx-auto mt-3 size-40">
                    <p class="mt-2 text-xs text-gray-500">Scan at the front desk to check in / out.</p>
                    <a href="{{ route('gym.members.qr', [$m, 'download' => 1]) }}" class="btn-secondary btn-sm mt-3">Download QR</a>
                </div>

                <div class="card card-body space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Visits (30 days)</span><span class="font-medium">{{ $attendanceCount30 }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Portal login</span><span class="font-medium">{{ $m->user_id ? 'Enabled' : 'Not set' }}</span></div>
                    @if ($m->enquiry)
                        <div class="flex justify-between"><span class="text-gray-500">Converted from</span><a href="{{ route('gym.enquiries.show', $m->enquiry) }}" class="font-medium text-indigo-600 hover:underline">Enquiry #{{ $m->enquiry->id }}</a></div>
                    @endif
                    <div>
                        <p class="mb-1 text-gray-500">Trainer</p>
                        @if ($canManage && $trainers->isNotEmpty())
                            <select wire:change="assignTrainer($event.target.value)" class="input">
                                <option value="">No trainer</option>
                                @foreach ($trainers as $t)<option value="{{ $t->id }}" @selected($m->trainer_id === $t->id)>{{ $t->name }}</option>@endforeach
                            </select>
                        @else
                            <p class="font-medium">{{ $m->trainer?->name ?? '—' }}</p>
                        @endif
                    </div>
                </div>

                @can('gym.settings')
                    <livewire:gym.members.member-devices :member="$m" :key="'md-'.$m->id" />
                @endcan

                @if ($canManage)
                    <div class="card card-body space-y-2">
                        @if ($m->status->value === 'pending')
                            <p class="text-xs text-gray-500">Signed up from the app. Approve to let them log in; assign a plan as usual.</p>
                            <button wire:click="toggleStatus" class="btn-primary w-full">Approve registration</button>
                        @else
                            <button wire:click="toggleStatus" class="btn-secondary w-full">Mark as {{ $m->status->value === 'active' ? 'inactive' : 'active' }}</button>
                        @endif
                        <button wire:click="delete" wire:confirm="Delete {{ $m->name }}? Their history is kept but they will no longer appear in lists." class="btn-ghost w-full text-red-600">Delete member</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($tab === 'memberships')
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Plan</th><th>Period</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($memberships as $ms)
                        @php($status = $ms->computedStatus())
                        <tr wire:key="ms-{{ $ms->id }}">
                            <td><p class="font-medium text-gray-900">{{ $ms->plan_name }}</p><p class="text-xs text-gray-500">{{ $ms->duration_days }} days @if($ms->renewed_from_id)· renewal @endif</p></td>
                            <td>
                                {{ $ms->start_date->format('d M Y') }} – {{ $ms->end_date->format('d M Y') }}
                                @foreach ($ms->freezes as $f)
                                    <span wire:key="fz-{{ $f->id }}" class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-sky-700">
                                        <x-ui.icon name="clock" class="size-3.5" /> Frozen {{ $f->start_date->format('d M') }} – {{ $f->end_date->format('d M') }} ({{ $f->days }} {{ \Illuminate\Support\Str::plural('day', $f->days) }})
                                        @if ($canMembership && $f->end_date->gte(today()))
                                            <button wire:click="endFreeze({{ $f->id }})" wire:confirm="{{ $f->start_date->gt(today()) ? 'Remove this planned freeze?' : 'End this freeze today? Unused days come off the end date.' }}" class="font-medium underline">{{ $f->start_date->gt(today()) ? 'Remove' : 'End now' }}</button>
                                        @endif
                                    </span>
                                @endforeach
                            </td>
                            <td>{{ money($ms->total_amount) }} @if((float) $ms->discount > 0)<span class="block text-xs text-gray-500">−{{ money($ms->discount) }} discount</span>@endif</td>
                            <td>{{ money($ms->amount_paid) }}</td>
                            <td class="{{ $ms->balance() > 0 && $status->value !== 'cancelled' ? 'font-medium text-amber-700' : '' }}">{{ $status->value === 'cancelled' ? '—' : money($ms->balance()) }}</td>
                            <td><x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge></td>
                            <td class="space-x-2 text-right">
                                @if ($canPay && $status->value !== 'cancelled')
                                    <a href="{{ route('gym.memberships.invoice', $ms) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">Invoice</a>
                                    @if ($ms->balance() > 0)
                                        <button wire:click="openPaymentModal({{ $ms->id }})" class="text-xs font-medium text-emerald-600 hover:underline">Collect</button>
                                    @endif
                                @endif
                                @if ($canMembership && $ms->max_freeze_days > 0 && $ms->freezeDaysLeft() > 0 && in_array($status->value, ['active', 'upcoming'], true))
                                    <button wire:click="openFreeze({{ $ms->id }})" class="text-xs font-medium text-sky-700 hover:underline">Freeze</button>
                                @endif
                                @if ($canMembership && $status->value !== 'cancelled' && (float) $ms->amount_paid === 0.0)
                                    <button wire:click="cancelMembership({{ $ms->id }})" wire:confirm="Cancel this membership?" class="text-xs font-medium text-red-600 hover:underline">Cancel</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty message="No memberships yet." icon="card" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'payments')
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Receipt</th><th>Date</th><th>Plan</th><th>Method</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr wire:key="p-{{ $p->id }}">
                            <td class="font-medium">{{ $p->receipt_number }}</td>
                            <td>{{ $p->paid_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $p->membership?->plan_name ?? '—' }}</td>
                            <td>{{ $p->method->label() }} @if($p->reference)<span class="block text-xs text-gray-500">{{ $p->reference }}</span>@endif</td>
                            <td class="font-semibold text-emerald-600">{{ money($p->amount) }}</td>
                            <td class="text-right">@if($canPay)<a href="{{ route('gym.payments.receipt', $p) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">Receipt</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty message="No payments recorded." icon="cash" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($payments->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $payments->links() }}</div>@endif
        </div>
    @endif

    @if ($tab === 'attendance')
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Duration</th><th>Method</th></tr></thead>
                <tbody>
                    @forelse ($attendance as $a)
                        <tr wire:key="a-{{ $a->id }}">
                            <td class="font-medium">{{ $a->date->format('D, d M Y') }}</td>
                            <td>{{ $a->check_in_at->format('h:i A') }}</td>
                            <td>{{ $a->check_out_at?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $a->durationLabel() ?? '—' }}</td>
                            <td><x-ui.badge :color="['qr' => 'indigo', 'self' => 'purple'][$a->method] ?? 'gray'">{{ strtoupper($a->method) }}</x-ui.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty message="No attendance recorded." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($attendance->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $attendance->links() }}</div>@endif
        </div>
    @endif

    @if ($tab === 'progress')
        <livewire:gym.members.weight-progress :member="$m" :key="'wp-'.$m->id" />
    @endif

    @if ($tab === 'ai')
        <livewire:gym.members.ai-progress :member="$m" :key="'ai-'.$m->id" />
    @endif

    @if ($tab === 'plans')
        <livewire:gym.members.member-programs :member="$m" :key="'mp-'.$m->id" />
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">Customised plans</h2>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600">Customised plans only {{ $m->name }} can see — shown in their app next to your default diet charts and workout plans.</p>
            @if ($canCustomPlans)
                <div class="flex gap-2">
                    <a href="{{ route('gym.content.create', ['type' => 'diet', 'member' => $m->id]) }}" class="btn-secondary btn-sm"><x-ui.icon name="plus" class="size-4" /> Diet chart</a>
                    <a href="{{ route('gym.content.create', ['type' => 'workout', 'member' => $m->id]) }}" class="btn-secondary btn-sm"><x-ui.icon name="plus" class="size-4" /> Workout plan</a>
                </div>
            @endif
        </div>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Plan</th><th>Type</th><th>Category</th><th>Sections</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                    @forelse ($customPlans as $cp)
                        <tr wire:key="cp-{{ $cp->id }}">
                            <td><a href="{{ route('gym.content.show', $cp) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $cp->title }}</a></td>
                            <td>{{ $cp->type->label() }}</td>
                            <td>{{ $cp->category?->name ?? '—' }}</td>
                            <td>{{ $cp->sections_count }}</td>
                            <td><x-ui.badge :color="$cp->is_published ? 'green' : 'amber'">{{ $cp->is_published ? 'Visible' : 'Draft' }}</x-ui.badge></td>
                            <td class="text-gray-500">{{ $cp->updated_at->diffForHumans() }}</td>
                            <td class="text-right">@can('update', $cp)<a href="{{ route('gym.content.edit', $cp) }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty message="No customised plans yet. The member sees your default diet charts and workout plans." icon="tag" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Freeze modal --}}
    @if ($canMembership)
        <x-ui.modal wire-model="showFreezeModal" title="Freeze membership">
            @php($fm = $freezeMembershipId ? $memberships->firstWhere('id', $freezeMembershipId) : null)
            <form wire:submit="saveFreeze" class="space-y-4">
                @if ($fm)
                    <p class="text-sm text-gray-600">{{ $fm->plan_name }}, ends {{ $fm->end_date->format('d M Y') }}. <strong>{{ $fm->freezeDaysLeft() }} of {{ $fm->max_freeze_days }}</strong> freeze days left. The end date moves out by the days you freeze.</p>
                @endif
                @error('freeze') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="From" name="freezeForm.start_date" required><input wire:model="freezeForm.start_date" type="date" min="{{ today()->toDateString() }}" class="input"></x-ui.field>
                    <x-ui.field label="Days" name="freezeForm.days" required><input wire:model="freezeForm.days" type="number" min="1" max="{{ $fm?->freezeDaysLeft() ?? 365 }}" class="input"></x-ui.field>
                </div>
                <x-ui.field label="Reason" name="freezeForm.reason"><input wire:model="freezeForm.reason" class="input" placeholder="e.g. Travelling, injury"></x-ui.field>
                <p class="hint">Check-ins are blocked while the membership is frozen. You can end a freeze early; unused days come off the end date again.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showFreezeModal', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Freeze</button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Assign / renew modal --}}
    @if ($canMembership)
        <x-ui.modal wire-model="showPlanModal" :title="$planMode === 'renew' ? 'Renew membership' : 'Assign membership plan'">
            <form wire:submit="savePlan" class="space-y-4">
                @error('start_date') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
                <x-ui.field label="Plan" name="planForm.plan_id" required>
                    <select wire:model.live="planForm.plan_id" class="input">
                        <option value="">Select a plan…</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} — {{ $plan->durationLabel() }} — {{ money($plan->price, false) }}</option>
                        @endforeach
                    </select>
                    @if ($plans->isEmpty())<p class="hint">No active plans. <a href="{{ route('gym.plans.index') }}" class="text-indigo-600 underline">Create one</a>.</p>@endif
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Start date" name="planForm.start_date" required><input wire:model="planForm.start_date" type="date" class="input"></x-ui.field>
                    <x-ui.field label="Discount (₹)" name="planForm.discount"><input wire:model.live.debounce.400ms="planForm.discount" type="number" min="0" step="0.01" class="input"></x-ui.field>
                </div>
                @php($joiningFee = $m->gym->feeSettings()['joining_fee'])
                @if ($planMode === 'assign' && $joiningFee > 0)
                    <label class="flex items-center gap-2 text-sm"><input wire:model.live="planForm.joining_fee" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Add joining fee ({{ money($joiningFee) }})</label>
                @endif
                <x-ui.field label="Notes" name="planForm.notes"><input wire:model="planForm.notes" class="input"></x-ui.field>

                @if ($canPay)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <label class="flex items-center gap-2 text-sm font-medium"><input wire:model.live="planForm.pay_now" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Record payment now</label>
                        @if ($planForm['pay_now'] ?? false)
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <x-ui.field label="Amount (₹)" name="planForm.amount" hint="Less than total = partial"><input wire:model="planForm.amount" type="number" min="0" step="0.01" class="input"></x-ui.field>
                                <x-ui.field label="Method" name="planForm.method">
                                    <select wire:model="planForm.method" class="input">@foreach (\App\Enums\PaymentMethod::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                                </x-ui.field>
                                <x-ui.field label="Reference" name="planForm.reference"><input wire:model="planForm.reference" class="input" placeholder="UPI / txn id"></x-ui.field>
                            </div>
                        @endif
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showPlanModal', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Payment modal --}}
    @if ($canPay)
        <x-ui.modal wire-model="showPaymentModal" title="Record payment">
            <form wire:submit="savePayment" class="space-y-4">
                <x-ui.field label="Membership" name="paymentForm.membership_id" required>
                    <select wire:model="paymentForm.membership_id" class="input">
                        <option value="">Select…</option>
                        @foreach ($openMemberships as $om)
                            <option value="{{ $om->id }}">{{ $om->plan_name }} ({{ $om->start_date->format('d M y') }}) — due {{ money($om->balance()) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                @error('amount') <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Amount (₹)" name="paymentForm.amount" required><input wire:model="paymentForm.amount" type="number" min="1" step="0.01" class="input"></x-ui.field>
                    <x-ui.field label="Method" name="paymentForm.method" required>
                        <select wire:model="paymentForm.method" class="input">@foreach (\App\Enums\PaymentMethod::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                    </x-ui.field>
                    <x-ui.field label="Reference" name="paymentForm.reference"><input wire:model="paymentForm.reference" class="input" placeholder="UPI / txn id"></x-ui.field>
                    <x-ui.field label="Paid at" name="paymentForm.paid_at" required><input wire:model="paymentForm.paid_at" type="datetime-local" class="input"></x-ui.field>
                </div>
                <x-ui.field label="Notes" name="paymentForm.notes"><input wire:model="paymentForm.notes" class="input"></x-ui.field>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showPaymentModal', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Record payment</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
