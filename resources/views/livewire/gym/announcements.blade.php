@php
    $gym = current_gym();
    $sample = [
        'member_name' => 'Riya Sharma', 'first_name' => 'Riya', 'gym_name' => $gym->name,
        'days' => 3, 'days_text' => 'in 3 days', 'plan' => 'Monthly', 'end_date' => now()->addDays(3)->format('d M'),
        'amount' => money(1500), 'receipt' => 'RCPT-1-00042', 'plan_type' => 'workout', 'program' => 'Weight Loss — Beginner',
        'start_text' => 'starts today', 'weight' => '68 kg', 'today_plan' => 'Full body strength A', 'diet' => 'Menu A', 'streak' => 5,
    ];
@endphp
<div>
    <x-ui.page-header title="Notifications" subtitle="Messages to your members' phones. They also appear in the notification list in the app." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Members with the app" :value="$stats['members_with_app']" icon="users" :hint="$phones.' phones registered'" />
        <x-ui.stat label="Waiting for approval" :value="$stats['pending']" icon="clock" :color="$stats['pending'] ? 'amber' : 'gray'" />
        <x-ui.stat label="Sent" :value="$stats['sent_30']" icon="bell" color="green" hint="Last 30 days" />
        <x-ui.stat label="Can still send today" :value="$stats['left_today']" icon="flag" :color="$stats['left_today'] ? 'gray' : 'red'" :hint="'Up to '.$limit.' a day'" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-gray-200">
        <button wire:click="$set('tab', 'custom')" class="tab {{ $tab === 'custom' ? 'tab-active' : '' }}">Custom notifications</button>
        <button wire:click="$set('tab', 'automatic')" class="tab {{ $tab === 'automatic' ? 'tab-active' : '' }}">Automatic notifications <span class="ml-1 rounded-full {{ $paused ? 'bg-gray-200 text-gray-600' : 'bg-emerald-100 text-emerald-700' }} px-2 py-0.5 text-xs font-semibold">{{ $paused ? 'Off' : $activeCount.' on' }}</span></button>
    </div>

    @if ($tab === 'custom')
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="space-y-6 lg:col-span-3">
                <form wire:submit="submit" class="card card-body space-y-4">
                    <div>
                        <h2 class="card-title flex items-center gap-2"><x-ui.icon name="bell" class="size-5 text-indigo-500" /> New notification</h2>
                        <p class="mt-1 text-sm text-gray-500">Fitnix reviews every custom notification before it can be sent.</p>
                    </div>

                    <x-ui.field label="Title" name="title" required>
                        <input wire:model.live.debounce.300ms="title" maxlength="{{ \App\Services\NotificationSettings::TITLE_MAX }}" class="input" placeholder="Gym closed on Sunday">
                    </x-ui.field>
                    <x-ui.field label="Message" name="body" required>
                        <textarea wire:model.live.debounce.300ms="body" maxlength="{{ \App\Services\NotificationSettings::ANNOUNCEMENT_BODY_MAX }}" rows="5" class="input" placeholder="Hi {first_name}, we are closed this Sunday for maintenance. See you on Monday!"></textarea>
                        <p class="mt-1 flex justify-between gap-3 text-xs text-gray-400">
                            <span>You can use <code>{first_name}</code>, <code>{member_name}</code> and <code>{gym_name}</code>.</span>
                            <span>{{ mb_strlen($body) }}/{{ \App\Services\NotificationSettings::ANNOUNCEMENT_BODY_MAX }} · the phone shows the start, the app shows it all</span>
                        </p>
                    </x-ui.field>

                    <x-ui.field label="Picture (optional)" name="image" hint="JPG, PNG or WebP, up to 2 MB. Shown in the notification and on its detail page.">
                        <input type="file" wire:model="image" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-gray-200">
                        <div wire:loading wire:target="image" class="mt-1 text-xs text-gray-500">Uploading…</div>
                    </x-ui.field>

                    @if ($seesAll)
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.field label="Send to" name="audience" required>
                                <select wire:model.live="audience" class="input">
                                    @foreach ($audiences as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </x-ui.field>
                            @if ($audience === 'trainer')
                                <x-ui.field label="Trainer" name="trainerId" required>
                                    <select wire:model.live="trainerId" class="input">
                                        <option value="">Choose…</option>
                                        @foreach ($trainers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                                    </select>
                                </x-ui.field>
                            @endif
                        </div>
                    @else
                        <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">This goes to the members assigned to you.</p>
                    @endif

                    @if ($title !== '' || $body !== '')
                        <div class="rounded-2xl bg-gray-100 p-3">
                            <p class="mb-2 text-xs font-medium text-gray-500">Preview</p>
                            <div class="flex gap-3 rounded-xl bg-white p-3 shadow-sm">
                                @if ($gym->logoUrl())<img src="{{ $gym->logoUrl() }}" alt="" class="size-9 rounded-lg object-cover">@else<span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500"><x-ui.icon name="bell" class="size-5" /></span>@endif
                                <div class="min-w-0 text-sm">
                                    <p class="flex items-center justify-between gap-2 text-xs text-gray-400"><span>{{ $gym->name }}</span><span>now</span></p>
                                    <p class="font-semibold text-gray-900">{{ \App\Support\NotificationCatalog::render($title ?: 'Title', $sample) }}</p>
                                    <p class="line-clamp-3 text-gray-600">{{ \App\Support\NotificationCatalog::render($body ?: 'Message', $sample) }}</p>
                                    @if ($image && ! $errors->has('image'))<img src="{{ $image->temporaryUrl() }}" alt="" class="mt-2 max-h-32 rounded-lg object-cover">@endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-600"><strong>{{ $recipients }}</strong> {{ $recipients === 1 ? 'member' : 'members' }} · <strong>{{ $withApp }}</strong> with the app on their phone</p>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit,image">
                            <x-ui.icon name="check" class="size-4" /> Submit for approval
                        </button>
                    </div>
                </form>

                <div class="card overflow-x-auto">
                    <div class="card-header"><h2 class="card-title">Your notifications</h2></div>
                    <table class="table">
                        <thead><tr><th>Notification</th><th>To</th><th>Status</th><th>Reached</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($history as $a)
                                <tr wire:key="an-{{ $a->id }}">
                                    <td class="max-w-xs">
                                        <p class="flex items-center gap-1.5 font-medium text-gray-900">{{ $a->title }} @if ($a->image_path)<x-ui.icon name="camera" class="size-3.5 text-gray-400" />@endif</p>
                                        <p class="line-clamp-2 text-xs text-gray-500">{{ $a->body }}</p>
                                        <p class="mt-0.5 text-xs text-gray-400">{{ $a->created_at->format('d M') }}@if ($a->creator) · by {{ $a->creator->name }}@endif</p>
                                        @if ($a->status === \App\Models\Announcement::REJECTED && $a->rejection_reason)
                                            <p class="mt-1 rounded bg-red-50 px-2 py-1 text-xs text-red-700"><strong>Reason:</strong> {{ $a->rejection_reason }}</p>
                                        @endif
                                    </td>
                                    <td class="text-sm">{{ $a->audienceLabel() }}</td>
                                    <td class="text-sm whitespace-nowrap">
                                        <x-ui.badge :color="$a->statusColor()">{{ $a->statusLabel() }}</x-ui.badge>
                                        @if ($a->status === \App\Models\Announcement::SCHEDULED)<span class="mt-0.5 block text-xs text-gray-500">{{ $a->scheduled_at->format('d M, h:i A') }}</span>@endif
                                        @if ($a->status === \App\Models\Announcement::SENT)<span class="mt-0.5 block text-xs text-gray-500">{{ $a->sent_at->format('d M, h:i A') }}</span>@endif
                                    </td>
                                    <td class="text-sm whitespace-nowrap">
                                        @if ($a->status === \App\Models\Announcement::SENT)
                                            {{ $a->recipients_count }} {{ $a->recipients_count === 1 ? 'member' : 'members' }}
                                            <span class="block text-xs text-gray-500">{{ $a->pushed_count }} on phone</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($a->status === \App\Models\Announcement::APPROVED)
                                            <button wire:click="openSend({{ $a->id }})" class="btn-primary btn-sm"><x-ui.icon name="bell" class="size-4" /> Send</button>
                                        @endif
                                        @if (in_array($a->status, [\App\Models\Announcement::PENDING, \App\Models\Announcement::APPROVED, \App\Models\Announcement::REJECTED], true))
                                            <button wire:click="cancel({{ $a->id }})" data-confirm-title="Withdraw notification?" wire:confirm="Withdraw this notification?" class="ml-2 text-xs font-medium text-gray-500 hover:underline">Withdraw</button>
                                        @elseif ($a->status === \App\Models\Announcement::SCHEDULED)
                                            <button wire:click="cancel({{ $a->id }})" data-confirm-title="Cancel scheduled send?" wire:confirm="Cancel the scheduled send? It stays approved." class="text-xs font-medium text-red-600 hover:underline">Cancel send</button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($sendingId === $a->id)
                                    <tr wire:key="send-{{ $a->id }}">
                                        <td colspan="5" class="bg-indigo-50/60">
                                            <form wire:submit="send" class="flex flex-wrap items-center gap-4 text-sm">
                                                <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="when" value="now" class="text-indigo-600"> Send now</label>
                                                <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="when" value="later" class="text-indigo-600"> Schedule</label>
                                                @if ($when === 'later')<input type="datetime-local" wire:model="scheduledAt" class="input w-auto" min="{{ now()->format('Y-m-d\TH:i') }}">@endif
                                                <button type="submit" class="btn-primary btn-sm" wire:loading.attr="disabled">{{ $when === 'later' ? 'Schedule' : 'Send now' }}</button>
                                                <button type="button" wire:click="closeSend" class="text-xs text-gray-500 hover:underline">Close</button>
                                                <span class="w-full text-xs text-gray-500">Nothing is sent during quiet hours ({{ $quiet['start'] }} – {{ $quiet['end'] }}); "Send now" then goes out at {{ $quiet['end'] }}.</span>
                                                @error('scheduledAt') <span class="w-full text-xs text-red-600">{{ $message }}</span> @enderror
                                                @error('announcement') <span class="w-full text-xs text-red-600">{{ $message }}</span> @enderror
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5"><x-ui.empty message="No notifications yet." icon="bell" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="px-5 py-3">{{ $history->links() }}</div>
                </div>
            </div>

            <div class="space-y-6 lg:col-span-2">
                <div class="card card-body text-sm text-gray-600">
                    <h2 class="card-title">How it works</h2>
                    <ol class="mt-3 space-y-3">
                        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">1</span> Write your message and submit it.</li>
                        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">2</span> Fitnix checks it to keep notifications friendly and relevant for members.</li>
                        <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">3</span> Once approved, press <strong>Send</strong>: now or at a time you choose.</li>
                    </ol>
                    <ul class="mt-4 list-disc space-y-1 border-t border-gray-100 pt-3 pl-5">
                        <li>Only members of {{ $gym->name }} receive your notifications.</li>
                        <li>Up to {{ $limit }} a day; nothing during quiet hours ({{ $quiet['start'] }} – {{ $quiet['end'] }}).</li>
                        <li>Members can turn off gym news in the app. Membership and payment updates always reach them.</li>
                    </ul>
                </div>
            </div>
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat label="Turned on" :value="$paused ? 'Off' : $activeCount.' of '.count($catalog)" icon="bell" :color="$paused ? 'red' : 'green'" />
            <x-ui.stat label="Sent today" :value="$sent['today']" icon="clock" color="blue" />
            <x-ui.stat label="Sent in 30 days" :value="$sent['month']" icon="chart" :hint="$sent['on_phone'].' reached a phone'" />
            <x-ui.stat label="Members reached" :value="$sent['members']" icon="users" hint="Last 30 days" />
        </div>

        <div class="mb-6 card card-body flex flex-col gap-4 sm:flex-row sm:items-center {{ $paused ? 'border-red-200 bg-red-50/50' : '' }}">
            <div class="flex-1">
                <p class="font-semibold text-gray-900">{{ $paused ? 'Automatic notifications are off' : 'Automatic notifications are on' }}</p>
                <p class="text-sm text-gray-500">{{ $paused ? 'Your members receive no automatic notifications. Your choices below are kept for when you turn them back on.' : 'Turn this off to stop every automatic notification to your members at once.' }}</p>
            </div>
            @if ($canEditSettings)
                <button wire:click="togglePause" @if ($paused) data-confirm-title="Turn notifications on?" wire:confirm.primary="Turn automatic notifications back on?" @else data-confirm-title="Turn off notifications?" wire:confirm="Stop all automatic notifications to your members?" @endif
                    class="{{ $paused ? 'btn-primary' : 'btn-secondary text-red-600' }}">
                    {{ $paused ? 'Turn all on' : 'Turn all off' }}
                </button>
            @endif
        </div>

        <div class="card overflow-hidden {{ $paused ? 'opacity-60' : '' }}">
            <div class="card-header">
                <h2 class="card-title">Automatic notifications</h2>
                <p class="mt-1 text-sm text-gray-500">Sent by Fitnix without you doing anything. You choose which ones and when; the wording is set by Fitnix. Each one reaches a member only once.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($catalog as $key => $item)
                    @php $tpl = $templates[$key]; @endphp
                    <li wire:key="auto-{{ $key }}" class="grid gap-4 px-5 py-4 lg:grid-cols-5">
                        <div class="lg:col-span-2">
                            @php $on = $automatic[$key]['enabled'] ?? false; @endphp
                            <div class="flex items-start gap-3">
                                <button type="button" wire:click="toggleAutomatic('{{ $key }}')" role="switch" aria-checked="{{ $on && $tpl['is_enabled'] ? 'true' : 'false' }}" aria-label="{{ $item['label'] }}"
                                    class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ $on && $tpl['is_enabled'] ? 'bg-emerald-500' : 'bg-gray-300' }} disabled:cursor-not-allowed"
                                    @disabled(! $canEditSettings || ! $tpl['is_enabled'])>
                                    <span class="inline-block size-4 rounded-full bg-white shadow transition {{ $on && $tpl['is_enabled'] ? 'translate-x-4.5' : 'translate-x-0.5' }}"></span>
                                </button>
                                <span>
                                    <span class="block font-medium text-gray-900">{{ $item['label'] }}</span>
                                    <span class="block text-xs text-gray-500">{{ $item['description'] }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">
                                        Sent: <strong class="text-gray-800">{{ $sent['by_type'][$key]['today'] }}</strong> today ·
                                        <strong class="text-gray-800">{{ $sent['by_type'][$key]['month'] }}</strong> in 30 days
                                    </span>
                                    @if (! $tpl['is_enabled'])<span class="mt-1 inline-block"><x-ui.badge color="gray">Turned off by Fitnix</x-ui.badge></span>@endif
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2 text-sm lg:col-span-2">
                            @if ($item['kind'] === 'scheduled')
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="w-16 text-gray-500">Time</span>
                                    <select wire:model="automatic.{{ $key }}.time" class="input w-auto py-1" @disabled(! $canEditSettings)>
                                        @foreach ($timeOptions as $t)<option value="{{ $t }}">{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}</option>@endforeach
                                    </select>
                                </div>
                            @else
                                <p class="text-gray-500">Sent right away when it happens (after quiet hours if at night).</p>
                            @endif
                            @foreach ($item['params'] ?? [] as $param => $definition)
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-gray-500">{{ $definition['label'] }}</span>
                                    @if ($definition['type'] === 'days')
                                        @foreach (\App\Support\NotificationCatalog::EXPIRY_DAY_OPTIONS as $d)
                                            <label class="inline-flex items-center gap-1 rounded border border-gray-200 px-2 py-0.5 text-xs">
                                                <input type="checkbox" wire:model="automatic.{{ $key }}.params.{{ $param }}" value="{{ $d }}" class="rounded text-indigo-600" @disabled(! $canEditSettings)> {{ $d }}
                                            </label>
                                        @endforeach
                                    @elseif ($definition['type'] === 'weekday')
                                        <select wire:model="automatic.{{ $key }}.params.{{ $param }}" class="input w-auto py-1" @disabled(! $canEditSettings)>
                                            @foreach (\App\Support\NotificationCatalog::WEEKDAYS as $n => $day)<option value="{{ $n }}">{{ $day }}</option>@endforeach
                                        </select>
                                    @else
                                        <input type="number" wire:model="automatic.{{ $key }}.params.{{ $param }}" min="{{ $definition['min'] ?? 0 }}" max="{{ $definition['max'] ?? 100 }}" class="input w-20 py-1" @disabled(! $canEditSettings)>
                                    @endif
                                </div>
                            @endforeach
                            <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs">
                                <p class="font-semibold text-gray-800">{{ \App\Support\NotificationCatalog::render($tpl['title'], $sample) }}</p>
                                <p class="text-gray-600">{{ \App\Support\NotificationCatalog::render($tpl['body'], $sample) }}</p>
                            </div>
                        </div>

                        <div class="flex items-start justify-end gap-3">
                            @if ($canEditSettings && $tpl['is_enabled'])
                                <button wire:click="resetAutomatic('{{ $key }}')" class="text-xs font-medium text-gray-500 hover:underline">Reset</button>
                                <button wire:click="saveAutomatic('{{ $key }}')" class="btn-secondary btn-sm">Save</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @error('time') <p class="px-5 pb-4 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
