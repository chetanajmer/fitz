@php
    $sample = [
        'member_name' => 'Riya Sharma', 'first_name' => 'Riya', 'gym_name' => 'Iron Temple Fitness',
        'days' => 3, 'days_text' => 'in 3 days', 'plan' => 'Monthly', 'end_date' => now()->addDays(3)->format('d M'),
        'amount' => money(1500), 'receipt' => 'RCPT-1-00042', 'plan_type' => 'workout', 'program' => 'Weight Loss — Beginner',
        'start_text' => 'starts today', 'weight' => '68 kg', 'today_plan' => 'Full body strength A', 'diet' => 'Menu A', 'streak' => 5,
    ];
@endphp
<div>
    <x-ui.page-header title="App notifications" subtitle="Approve what gyms send to their members, and control the wording and timing of every automatic notification." />

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
        <button wire:click="$set('tab', 'approvals')" class="tab {{ $tab === 'approvals' ? 'tab-active' : '' }}">
            Approvals @if ($pendingCount)<span class="ml-1 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white">{{ $pendingCount }}</span>@endif
        </button>
        <button wire:click="$set('tab', 'templates')" class="tab {{ $tab === 'templates' ? 'tab-active' : '' }}">Templates</button>
        <button wire:click="$set('tab', 'rules')" class="tab {{ $tab === 'rules' ? 'tab-active' : '' }}">Rules</button>
    </div>

    @if ($tab === 'approvals')
        <div class="mb-4 inline-flex rounded-lg border border-gray-300 bg-white p-0.5 text-sm">
            <button wire:click="$set('queue', 'pending')" class="rounded-md px-3 py-1.5 font-medium {{ $queue === 'pending' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Waiting ({{ $pendingCount }})</button>
            <button wire:click="$set('queue', 'reviewed')" class="rounded-md px-3 py-1.5 font-medium {{ $queue === 'reviewed' ? 'bg-gray-900 text-white' : 'text-gray-600' }}">Reviewed</button>
        </div>

        <div class="space-y-4">
            @forelse ($list as $a)
                <div wire:key="ap-{{ $a->id }}" class="card card-body">
                    <div class="flex flex-col gap-4 lg:flex-row">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-gray-500">
                                <strong class="text-gray-800">{{ $a->gym?->name }}</strong> · {{ $a->creator?->name ?? 'Unknown' }} · {{ $a->created_at->format('d M, h:i A') }}
                            </p>
                            <div class="mt-2 rounded-xl bg-gray-50 p-3">
                                <p class="font-semibold text-gray-900">{{ $a->title }}</p>
                                <p class="mt-0.5 text-sm whitespace-pre-line text-gray-700">{{ $a->body }}</p>
                                @if ($a->imageUrl())<img src="{{ $a->imageUrl() }}" alt="" class="mt-2 max-h-40 rounded-lg object-cover">@endif
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                To: {{ $a->audienceLabel() }}
                                @isset($audienceSizes[$a->id]) · {{ $audienceSizes[$a->id] }} {{ $audienceSizes[$a->id] === 1 ? 'member' : 'members' }} @endisset
                                · Preview: <em>{{ \App\Support\NotificationCatalog::render($a->title, $sample) }}</em>
                            </p>
                        </div>

                        <div class="shrink-0 lg:w-64">
                            @if ($a->status === \App\Models\Announcement::PENDING)
                                @if ($rejectingId === $a->id)
                                    <form wire:submit="reject" class="space-y-2">
                                        <textarea wire:model="reason" rows="3" maxlength="500" class="input text-sm" placeholder="Why can it not be sent? The gym sees this."></textarea>
                                        @error('reason') <p class="input-error">{{ $message }}</p> @enderror
                                        <div class="flex justify-end gap-2">
                                            <button type="button" wire:click="cancelReject" class="btn-secondary btn-sm">Back</button>
                                            <button type="submit" class="btn btn-sm bg-red-600 text-white hover:bg-red-700">Reject</button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex gap-2 lg:flex-col">
                                        <button wire:click="approve({{ $a->id }})" class="btn-primary flex-1"><x-ui.icon name="check" class="size-4" /> Approve</button>
                                        <button wire:click="startReject({{ $a->id }})" class="btn-secondary flex-1"><x-ui.icon name="x" class="size-4" /> Reject</button>
                                    </div>
                                @endif
                            @else
                                <x-ui.badge :color="$a->statusColor()">{{ $a->statusLabel() }}</x-ui.badge>
                                <p class="mt-1 text-xs text-gray-500">{{ $a->reviewed_at?->format('d M, h:i A') }}@if ($a->reviewer) · {{ $a->reviewer->name }}@endif</p>
                                @if ($a->rejection_reason)<p class="mt-1 text-xs text-red-700">{{ $a->rejection_reason }}</p>@endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="card"><x-ui.empty :message="$queue === 'pending' ? 'Nothing waiting for approval.' : 'No reviewed notifications yet.'" icon="check" /></div>
            @endforelse
            <div>{{ $list->links() }}</div>
        </div>
    @elseif ($tab === 'templates')
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Automatic notification templates</h2>
                <p class="mt-1 text-sm text-gray-500">Used by every gym. Gyms can switch a notification off and change its time and rules, never the wording. Placeholders in <code>{braces}</code> are filled in by Fitnix.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($catalog as $key => $item)
                    @php $tpl = $templates[$key]; @endphp
                    <li wire:key="tpl-{{ $key }}" class="px-5 py-4">
                        @if ($editing === $key)
                            <form wire:submit="saveTemplate" class="space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-gray-900">{{ $item['label'] }}</p>
                                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_enabled" class="rounded text-indigo-600"> Active for all gyms</label>
                                </div>
                                <x-ui.field label="Title" name="form.title" required>
                                    <input wire:model.live.debounce.300ms="form.title" maxlength="{{ \App\Services\NotificationSettings::TITLE_MAX }}" class="input">
                                </x-ui.field>
                                <x-ui.field label="Message" name="form.body" required>
                                    <textarea wire:model.live.debounce.300ms="form.body" maxlength="{{ \App\Services\NotificationSettings::BODY_MAX }}" rows="2" class="input"></textarea>
                                </x-ui.field>
                                <div class="flex flex-wrap gap-1.5 text-xs">
                                    @foreach (\App\Support\NotificationCatalog::placeholders($key) as $placeholder => $help)
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-gray-700" title="{{ $help }}"><code>{{ '{'.$placeholder.'}' }}</code> {{ $help }}</span>
                                    @endforeach
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @if ($item['kind'] === 'scheduled')
                                        <x-ui.field label="Default time" name="form.send_time">
                                            <select wire:model="form.send_time" class="input">
                                                @foreach ($timeOptions as $t)<option value="{{ $t }}">{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}</option>@endforeach
                                            </select>
                                        </x-ui.field>
                                    @endif
                                    @foreach ($item['params'] ?? [] as $param => $definition)
                                        <x-ui.field :label="'Default: '.strtolower($definition['label'])">
                                            @if ($definition['type'] === 'days')
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach (\App\Support\NotificationCatalog::EXPIRY_DAY_OPTIONS as $d)
                                                        <label class="inline-flex items-center gap-1 rounded border border-gray-200 px-2 py-1 text-xs"><input type="checkbox" wire:model="form.params.{{ $param }}" value="{{ $d }}" class="rounded text-indigo-600"> {{ $d }}</label>
                                                    @endforeach
                                                </div>
                                            @elseif ($definition['type'] === 'weekday')
                                                <select wire:model="form.params.{{ $param }}" class="input">@foreach (\App\Support\NotificationCatalog::WEEKDAYS as $n => $day)<option value="{{ $n }}">{{ $day }}</option>@endforeach</select>
                                            @else
                                                <input type="number" wire:model="form.params.{{ $param }}" min="{{ $definition['min'] ?? 0 }}" max="{{ $definition['max'] ?? 100 }}" class="input">
                                            @endif
                                        </x-ui.field>
                                    @endforeach
                                </div>
                                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500">Preview</p>
                                    <p class="font-semibold text-gray-900">{{ \App\Support\NotificationCatalog::render($form['title'], $sample) }}</p>
                                    <p class="text-gray-600">{{ \App\Support\NotificationCatalog::render($form['body'], $sample) }}</p>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="resetTemplate('{{ $key }}')" data-confirm-title="Reset template?" wire:confirm.primary="Put this template back to the Fitnix default?" class="mr-auto text-xs font-medium text-gray-500 hover:underline">Reset to default</button>
                                    <button type="button" wire:click="cancelEdit" class="btn-secondary">Cancel</button>
                                    <button type="submit" class="btn-primary">Save</button>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-2 font-medium text-gray-900">
                                        {{ $item['label'] }}
                                        @if (! $tpl['is_enabled'])<x-ui.badge color="gray">Inactive for all gyms</x-ui.badge>@endif
                                        <span class="text-xs font-normal text-gray-500">
                                            {{ $item['kind'] === 'scheduled' ? 'Default '.\Illuminate\Support\Carbon::createFromFormat('H:i', $tpl['send_time'])->format('h:i A') : 'Sent when it happens' }}
                                            @isset($tpl['params']['days']) · {{ implode(', ', $tpl['params']['days']) }} days before @endisset
                                            @isset($tpl['params']['weekday']) · {{ \App\Support\NotificationCatalog::WEEKDAYS[$tpl['params']['weekday']] }}s @endisset
                                            @isset($tpl['params']['min_streak']) · {{ $tpl['params']['min_streak'] }}+ day streaks @endisset
                                        </span>
                                    </p>
                                    <p class="mt-1 text-sm"><span class="font-semibold text-gray-800">{{ $tpl['title'] }}</span> <span class="text-gray-600">{{ $tpl['body'] }}</span></p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <button type="button" wire:click="toggleTemplate('{{ $key }}')" role="switch" aria-checked="{{ $tpl['is_enabled'] ? 'true' : 'false' }}"
                                        title="{{ $tpl['is_enabled'] ? 'Active for all gyms: click to deactivate' : 'Inactive: click to activate' }}"
                                        class="inline-flex items-center gap-2 text-xs font-medium {{ $tpl['is_enabled'] ? 'text-emerald-700' : 'text-gray-500' }}">
                                        <span class="relative inline-flex h-5 w-9 items-center rounded-full transition {{ $tpl['is_enabled'] ? 'bg-emerald-500' : 'bg-gray-300' }}">
                                            <span class="inline-block size-4 rounded-full bg-white shadow transition {{ $tpl['is_enabled'] ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                                        </span>
                                        {{ $tpl['is_enabled'] ? 'Active' : 'Inactive' }}
                                    </button>
                                    <button wire:click="edit('{{ $key }}')" class="btn-secondary btn-sm">Edit</button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div wire:poll.30s class="card card-body mb-6 max-w-3xl">
            <div class="flex items-center justify-between gap-3">
                <h2 class="card-title">System status</h2>
                <span class="text-xs text-gray-500">Updates every 30 seconds</span>
            </div>
            <p class="mt-1 text-sm text-gray-500">Scheduled and automatic notifications only go out when the scheduler (cron) and the queue run on their own.</p>
            <ul class="mt-4 divide-y divide-gray-100 text-sm">
                @foreach ($health as $key => $check)
                    <li wire:key="health-{{ $key }}" class="flex items-start gap-3 py-2.5">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full {{ $check['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            <x-ui.icon :name="$check['ok'] ? 'check' : 'x'" class="size-3.5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-medium text-gray-900">{{ $check['label'] }}</span>
                            <span class="block break-words text-xs text-gray-500">{{ $check['detail'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <form wire:submit="saveRules" class="card card-body max-w-xl space-y-4">
            <h2 class="card-title">Rules for all gyms</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field label="Quiet hours start" name="quietStart" hint="No notification reaches a phone after this time.">
                    <input type="time" wire:model="quietStart" class="input">
                </x-ui.field>
                <x-ui.field label="Quiet hours end" name="quietEnd" hint="Notifications held overnight go out at this time.">
                    <input type="time" wire:model="quietEnd" class="input">
                </x-ui.field>
            </div>
            <x-ui.field label="Custom notifications per gym per day" name="dailyLimit" hint="After approval. Automatic notifications are not counted.">
                <input type="number" wire:model="dailyLimit" min="1" max="20" class="input w-28">
            </x-ui.field>
            <div class="text-right"><button type="submit" class="btn-primary">Save rules</button></div>
        </form>
    @endif
</div>
