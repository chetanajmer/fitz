<div>
    <div class="card card-body mb-6 flex flex-col gap-4 md:flex-row md:items-center">
        @if ($g->logoUrl())
            <img src="{{ $g->logoUrl() }}" alt="" class="size-16 rounded-xl object-contain ring-1 ring-gray-200">
        @else
            <div class="flex size-16 items-center justify-center rounded-xl text-xl font-bold text-white" style="background: {{ $g->primaryColor() }}">{{ $g->initials() }}</div>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">{{ $g->name }}</h1>
                <x-ui.badge :color="$g->status->color()">{{ $g->status->label() }}</x-ui.badge>
            </div>
            <p class="text-sm text-gray-500">/{{ $g->slug }} · {{ $g->city }} · registered {{ $g->created_at->format('d M Y') }}</p>
            <p class="text-sm text-gray-500">Owner: <strong class="text-gray-700">{{ $g->owner?->name }}</strong> · {{ $g->owner?->email }} · {{ $g->owner?->phone }}</p>
            @if ($g->status_reason)<p class="mt-1 text-sm text-amber-700">Reason: {{ $g->status_reason }} ({{ $g->status_changed_at?->format('d M Y') }})</p>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $g->publicUrl() }}" target="_blank" class="btn-secondary btn-sm">Public page <x-ui.icon name="external" class="size-3.5" /></a>
            @if ($g->status->value !== 'active')<button wire:click="openStatus('active')" class="btn-success btn-sm">Activate</button>@endif
            @if ($g->status->value !== 'suspended')<button wire:click="openStatus('suspended')" class="btn-secondary btn-sm">Suspend</button>@endif
            @if ($g->status->value !== 'deactivated')<button wire:click="openStatus('deactivated')" class="btn-danger btn-sm">Deactivate</button>@endif
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-ui.stat label="Members" :value="$counts['members']" icon="users" />
        <x-ui.stat label="Billable now" :value="$counts['billable']" icon="check" color="green" />
        <x-ui.stat label="Trainers" :value="$counts['trainers']" icon="whistle" color="gray" />
        <x-ui.stat label="This month (est.)" :value="money($estimate['amount'], false)" icon="receipt" color="blue" :hint="$estimate['members'].' × '.money($estimate['rate']).($estimate['custom'] ? ' (custom price)' : '')" />
        <x-ui.stat label="Outstanding" :value="money($outstanding, false)" icon="cash" :color="$outstanding > 0 ? 'red' : 'green'" />
    </div>

    <div class="mb-4 flex gap-6 border-b border-gray-200">
        <button wire:click="$set('tab', 'members')" class="tab {{ $tab === 'members' ? 'tab-active' : '' }}">Members</button>
        <button wire:click="$set('tab', 'invoices')" class="tab {{ $tab === 'invoices' ? 'tab-active' : '' }}">Invoices</button>
        <button wire:click="$set('tab', 'pricing')" class="tab {{ $tab === 'pricing' ? 'tab-active' : '' }}">Pricing @if($pricing['custom'])<x-ui.badge color="purple" class="ml-1">Custom</x-ui.badge>@endif</button>
        <button wire:click="$set('tab', 'access')" class="tab {{ $tab === 'access' ? 'tab-active' : '' }}">Access & login</button>
    </div>

    @if ($tab === 'members')
        <div class="card">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search members…" class="input flex-1">
                <select wire:model.live="memberFilter" class="input md:w-48">
                    <option value="">All members</option>
                    <option value="billable">Billable (active + valid)</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Member</th><th>Phone</th><th>Joined</th><th>Plan</th><th>Valid till</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($members as $m)
                            <tr wire:key="m-{{ $m->id }}">
                                <td><p class="font-medium">{{ $m->name }}</p><p class="text-xs text-gray-500">{{ $m->member_code }}</p></td>
                                <td>{{ $m->phone }}</td>
                                <td>{{ $m->joining_date->format('d M Y') }}</td>
                                <td>{{ $m->latestMembership?->plan_name ?? '—' }}</td>
                                <td>{{ $m->latestMembership?->end_date->format('d M Y') ?? '—' }}</td>
                                <td><x-ui.badge :color="$m->status->color()">{{ $m->status->label() }}</x-ui.badge></td>
                                <td class="text-right">
                                    @if ($m->user_id)
                                        <button wire:click="magicLink({{ $m->user_id }})" class="text-xs font-medium text-indigo-600 hover:underline">Login as</button>
                                    @else
                                        <span class="text-xs text-gray-400" title="No member portal login">App only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty message="No members." icon="users" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($members->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $members->links() }}</div>@endif
        </div>
    @elseif ($tab === 'invoices')
        <div class="card">
            <div class="card-header"><h2 class="card-title">SaaS invoices</h2><button wire:click="generateInvoice" class="btn-secondary btn-sm">Generate last month</button></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Period</th><th>Members</th><th>Rate</th><th>Total</th><th>Paid</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr wire:key="i-{{ $inv->id }}">
                                <td class="font-medium">{{ $inv->invoice_number }}</td>
                                <td>{{ $inv->period_start->format('M Y') }}</td>
                                <td>{{ $inv->active_members }}</td>
                                <td>{{ money($inv->rate_per_member) }}</td>
                                <td class="font-semibold">{{ money($inv->total) }}</td>
                                <td>{{ money($inv->amount_paid) }}</td>
                                <td><x-ui.badge :color="$inv->status->color()">{{ $inv->status->label() }}</x-ui.badge></td>
                                <td class="text-right"><a href="{{ route('platform.invoices.show', $inv) }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty message="No invoices yet." icon="receipt" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())<div class="border-t border-gray-100 px-4 py-3">{{ $invoices->links() }}</div>@endif
        </div>
    @endif

    @if ($tab === 'pricing')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6">
                <div class="card card-body">
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">Current price</p>
                    <p class="mt-2 text-4xl font-bold">{{ money($pricing['rate']) }}</p>
                    <p class="text-sm text-gray-500">per active member / month</p>
                    @if ($pricing['custom'])
                        <x-ui.badge color="purple" class="mt-3">Custom price</x-ui.badge>
                        <p class="mt-2 text-xs text-gray-500">
                            Since {{ $pricing['override']->effective_from->format('d M Y') }}{{ $pricing['override']->ends_on ? ' until '.$pricing['override']->ends_on->format('d M Y') : '' }}.
                            Standard price is {{ money($standardRate) }}.
                        </p>
                    @else
                        <x-ui.badge class="mt-3">Standard platform price</x-ui.badge>
                    @endif
                </div>

                <form wire:submit="setCustomPrice" class="card card-body space-y-4">
                    <h2 class="card-title">Set custom price</h2>
                    <x-ui.field label="Price per active member (₹)" name="customPrice" required><input wire:model="customPrice" type="number" step="0.01" min="0" class="input" placeholder="e.g. 30"></x-ui.field>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field label="From" name="customFrom" required><input wire:model="customFrom" type="date" class="input"></x-ui.field>
                        <x-ui.field label="Until (optional)" name="customUntil"><input wire:model="customUntil" type="date" class="input"></x-ui.field>
                    </div>
                    <x-ui.field label="Note" name="customNotes"><input wire:model="customNotes" class="input" placeholder="e.g. Premium support package"></x-ui.field>
                    <button type="submit" class="btn-primary w-full">Save custom price</button>
                    <p class="text-xs text-gray-500">Billing uses the price in force on the first day of each month. Invoices already issued never change. Leave "Until" empty to keep it until you change it.</p>
                    @if ($pricing['custom'] || $priceHistory->contains(fn ($o) => $o->effective_from->isFuture()))
                        <div class="border-t border-gray-100 pt-3">
                            <button type="button" wire:click="endCustomPrice" wire:confirm="Return {{ $g->name }} to the standard price from the 'From' date above?" class="btn-secondary w-full">Back to standard price from the "From" date</button>
                        </div>
                    @endif
                </form>
            </div>

            <div class="card lg:col-span-2">
                <div class="card-header"><h2 class="card-title">Custom price history</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Price</th><th>From</th><th>Until</th><th>Note</th><th>Set by</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($priceHistory as $o)
                                <tr wire:key="po-{{ $o->id }}">
                                    <td class="font-semibold">{{ money($o->price_per_member) }}</td>
                                    <td>{{ $o->effective_from->format('d M Y') }}</td>
                                    <td>{{ $o->ends_on?->format('d M Y') ?? 'Open-ended' }}</td>
                                    <td>{{ $o->notes ?: '—' }}</td>
                                    <td>{{ $o->creator?->name ?? '—' }}</td>
                                    <td>
                                        @if ($o->isCurrent())<x-ui.badge color="green">Active</x-ui.badge>
                                        @elseif ($o->effective_from->isFuture())<x-ui.badge color="blue">Scheduled</x-ui.badge>
                                        @else<x-ui.badge>Ended</x-ui.badge>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ui.empty :message="'No custom pricing — this gym pays the standard '.money($standardRate).' per active member.'" icon="tag" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($tab === 'access')
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Magic links</strong> open an account without its password. Each link works <strong>once</strong> and expires after {{ \App\Services\ImpersonationService::LINK_MINUTES }} minutes. Open it in a <strong>private/incognito window</strong> to keep this Super Admin session, or in this tab to switch, then use "Return to Super Admin" in the yellow bar. Every link is logged.
        </div>
        @error('impersonate') <p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="card lg:col-span-3">
                <div class="card-header"><h2 class="card-title">Accounts in this gym</h2><span class="text-xs text-gray-500">Members without a portal login use the mobile app (OTP) and can't be opened here.</span></div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($accounts as $u)
                        <li wire:key="acc-{{ $u->id }}" class="flex items-center gap-3 px-5 py-3">
                            <img src="{{ $u->photoUrl() }}" alt="" class="size-9 rounded-full object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $u->name }}
                                    @if ($u->id === $g->owner_id)<x-ui.badge color="indigo">Owner</x-ui.badge>@endif
                                    <x-ui.badge>{{ str_replace('_', ' ', (string) $u->getRoleNames()->first()) }}</x-ui.badge>
                                    @unless ($u->isActive())<x-ui.badge color="red">Inactive</x-ui.badge>@endunless
                                </p>
                                <p class="truncate text-xs text-gray-500">{{ $u->email }} · last login {{ $u->last_login_at?->diffForHumans() ?? 'never' }}</p>
                            </div>
                            @if ($u->isGymAdmin() || $u->isTrainer())
                                <button wire:click="openPassword({{ $u->id }})" class="btn-secondary btn-sm">Set password</button>
                            @endif
                            <button wire:click="magicLink({{ $u->id }})" class="btn-secondary btn-sm" @disabled(! $u->isActive())><x-ui.icon name="link" class="size-4" /> Magic link</button>
                        </li>
                    @empty
                        <li><x-ui.empty message="No accounts." icon="users" /></li>
                    @endforelse
                </ul>
            </div>
            <div class="card lg:col-span-2">
                <div class="card-header"><h2 class="card-title">Recent magic links</h2></div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($recentLinks as $t)
                        <li wire:key="tok-{{ $t->id }}" class="px-5 py-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate font-medium">{{ $t->user?->name ?? 'Deleted user' }}</span>
                                <x-ui.badge :color="['used' => 'green', 'expired' => 'gray', 'active' => 'amber'][$t->status()]">{{ ucfirst($t->status()) }}</x-ui.badge>
                            </div>
                            <p class="text-xs text-gray-500">
                                by {{ $t->creator?->name }} · {{ $t->created_at->format('d M, h:i A') }}
                                @if ($t->used_at) · used {{ $t->used_at->format('h:i A') }} from {{ $t->used_ip }} @endif
                            </p>
                        </li>
                    @empty
                        <li><x-ui.empty message="No magic links yet." icon="clock" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif

    <x-ui.modal wire-model="showLink" :title="'Magic link for '.$linkFor" max-width="xl">
        <div x-data="{ copied: false }" class="space-y-4">
            <p class="text-sm text-gray-600">Valid for <strong>{{ \App\Services\ImpersonationService::LINK_MINUTES }} minutes</strong>, single use. Don't share it with anyone else.</p>
            <div class="flex gap-2">
                <input readonly value="{{ $linkUrl }}" class="input font-mono text-xs" x-ref="link" @focus="$el.select()">
                <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)" x-text="copied ? 'Copied' : 'Copy'"></button>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                <strong>Recommended:</strong> copy it and paste it into a private/incognito window, so you stay logged in here.
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showLink', false)" class="btn-secondary">Close</button>
                <a href="{{ $linkUrl }}" class="btn-primary">Open in this tab</a>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal wire-model="showPassword" :title="'Set password for '.$passwordFor" max-width="md">
        {{-- Only rendered while open, so the browser cannot autofill a saved password into it. --}}
        @if ($showPassword)
            <form wire:submit="savePassword" class="space-y-4">
                <p class="text-sm text-gray-600">They are signed out of the website and the staff app, and sign in with this password from now on. Share it with them privately.</p>
                <x-ui.field label="New password" name="newPassword" required hint="At least 8 characters.">
                    <input wire:model="newPassword" type="password" class="input" autocomplete="new-password">
                </x-ui.field>
                <x-ui.field label="Confirm password" name="newPassword_confirmation" required>
                    <input wire:model="newPassword_confirmation" type="password" class="input" autocomplete="new-password">
                </x-ui.field>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showPassword', false)" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Set password</button>
                </div>
            </form>
        @endif
    </x-ui.modal>

    <x-ui.modal wire-model="showStatusModal" :title="'Set gym to '.ucfirst($newStatus)" max-width="md">
        <form wire:submit="changeStatus" class="space-y-4">
            <p class="text-sm text-gray-600">
                @if ($newStatus === 'active') The gym's staff and members regain access and the public page goes live.
                @elseif ($newStatus === 'suspended') Staff and members are blocked and the public page is hidden. Data is kept; you can reactivate anytime.
                @else The gym is closed: access is blocked, the public page is hidden and it is excluded from future billing. Data is kept. @endif
            </p>
            @if ($newStatus !== 'active')
                <x-ui.field label="Reason" name="reason" required><input wire:model="reason" class="input" placeholder="e.g. Unpaid invoices"></x-ui.field>
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showStatusModal', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="{{ $newStatus === 'deactivated' ? 'btn-danger' : 'btn-primary' }}">Confirm</button>
            </div>
        </form>
    </x-ui.modal>
</div>
