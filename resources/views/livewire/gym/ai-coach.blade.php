<div>
    <x-ui.page-header title="AI Coach" subtitle="AI progress reports and photo comparisons for your members, included in your Fitnix plan." />

    @if (! $platformEnabled || ! $settings->allowed)
        <div class="card card-body mb-6 flex items-start gap-3 border-amber-200 bg-amber-50 text-sm text-amber-800">
            <x-ui.icon name="warning" class="size-5 shrink-0" />
            <p>AI coach is not available for your gym yet. Contact Fitnix support to switch it on.</p>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Credits used this month" :value="$used.' / '.$pool" icon="sparkles" :color="$used >= $pool ? 'red' : 'brand'" :hint="$poolExtra > 0 ? 'Includes '.$poolExtra.' extra from Fitnix this month' : 'Gym pool set by Fitnix, resets on the 1st'" />
        <x-ui.stat label="Per member limit" :value="$settings->memberMonthlyLimit().' credits'" icon="user" color="blue" :hint="'Fitnix maximum: '.$settings->member_ceiling" />
        <x-ui.stat label="Photo comparison" :value="$settings->photos_allowed ? 'Included' : 'Not included'" icon="camera" :color="$settings->photos_allowed ? 'green' : 'gray'" />
        <x-ui.stat label="Members who consented" :value="$consentedCount" icon="shield" color="green" hint="Accepted the AI consent in the app" />
    </div>

    @if ($memberRequests->isNotEmpty())
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="card-title">Member credit requests</h2>
                <x-ui.badge color="amber">{{ $memberRequests->count() }} waiting</x-ui.badge>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Member</th><th>Asked for</th><th>Note</th><th>Requested</th><th>Grant</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($memberRequests as $request)
                            <tr wire:key="cr-{{ $request->id }}">
                                <td>
                                    @can('view', $request->member)
                                        <a href="{{ route('gym.members.show', ['member' => $request->member, 'tab' => 'ai']) }}" class="font-medium text-indigo-600 hover:underline">{{ $request->member->name }}</a>
                                    @else
                                        {{ $request->member->name }}
                                    @endcan
                                </td>
                                <td>{{ $request->credits }} {{ \Illuminate\Support\Str::plural('credit', $request->credits) }}</td>
                                <td class="max-w-xs text-gray-500">{{ $request->note ?? '—' }}</td>
                                <td class="text-gray-500">{{ $request->created_at->format('d M, h:i A') }}</td>
                                <td>
                                    <input wire:model="grant.{{ $request->id }}" type="number" min="1" max="{{ \App\Services\AiCreditRequestService::MEMBER_MAX }}" placeholder="{{ $request->credits }}" class="input w-20" aria-label="Credits to grant {{ $request->member->name }}">
                                    @error('grant.'.$request->id)<p class="input-error">{{ $message }}</p>@enderror
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" wire:click="approveMemberRequest({{ $request->id }})" class="btn-primary btn-sm">Approve</button>
                                    <button type="button" wire:click="rejectMemberRequest({{ $request->id }})" wire:confirm="Decline this request?" class="btn-secondary btn-sm">Decline</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="hint px-5 pb-4">Extra credits count for this month only and come out of your gym's pool ({{ max(0, $pool - $used) }} left).</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
        <form wire:submit="save" class="card">
            <div class="card-header"><h2 class="card-title">Settings</h2></div>
            <div class="card-body space-y-4">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input wire:model="enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600" @disabled(! $settings->allowed)> Switch on AI coach for my members
                </label>

                <x-ui.field label="Who can use it" name="eligibility">
                    <select wire:model.live="eligibility" class="input">
                        @foreach (\App\Models\GymAiSetting::ELIGIBILITY as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($eligibility === 'plans')
                    <x-ui.field label="Plans that include AI coach" name="planIds">
                        <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-3">
                            @forelse ($plans as $plan)
                                <label wire:key="plan-{{ $plan->id }}" class="flex items-center gap-2 text-sm"><input wire:model="planIds" type="checkbox" value="{{ $plan->id }}" class="rounded border-gray-300 text-indigo-600"> {{ $plan->name }}</label>
                            @empty
                                <p class="text-sm text-gray-500">No membership plans yet.</p>
                            @endforelse
                        </div>
                    </x-ui.field>
                @elseif ($eligibility === 'selected')
                    <p class="hint">{{ $selectedCount }} {{ \Illuminate\Support\Str::plural('member', $selectedCount) }} picked. Turn AI on for a member from their page → AI Progress tab.</p>
                @endif

                <x-ui.field label="Credits per member per month" name="memberLimit" :hint="'A progress report costs '.$costs['progress'].', a photo comparison '.$costs['photo_comparison'].'.'">
                    <input wire:model="memberLimit" type="number" min="1" max="{{ $settings->member_ceiling }}" class="input">
                </x-ui.field>

                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="autoMonthlyReport" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Send every eligible member a progress report on the 1st of each month
                </label>
                <p class="hint">Members must have a valid membership and accept the AI consent in the app. Their reports are visible to you.</p>
            </div>
            <div class="border-t border-gray-100 px-5 py-4"><button type="submit" class="btn-primary">Save</button></div>
        </form>

        @php $openPoolRequest = $poolRequests->first(fn ($r) => $r->isPending()); @endphp
        <div class="card">
            <div class="card-header"><h2 class="card-title">Need more credits?</h2></div>
            <div class="card-body space-y-4">
                @if ($openPoolRequest)
                    <p class="flex items-start gap-2 text-sm text-amber-800"><x-ui.icon name="clock" class="mt-0.5 size-4 shrink-0" /> You asked Fitnix for {{ $openPoolRequest->credits }} credits on {{ $openPoolRequest->created_at->format('d M') }}. Waiting for their answer.</p>
                @else
                    <form wire:submit="requestPoolCredits" class="space-y-3">
                        <p class="text-sm text-gray-500">
                            {{ $settings->allowed ? 'Ask Fitnix to add credits to your gym\'s pool for this month.' : 'Ask Fitnix to switch on AI coach and add credits for your gym.' }}
                            You get {{ $settings->monthly_credits }} credits every month; {{ max(0, $pool - $used) }} left now.
                        </p>
                        <x-ui.field label="Credits" name="poolCredits">
                            <input wire:model="poolCredits" type="number" min="{{ \App\Services\AiCreditRequestService::GYM_MIN }}" max="{{ \App\Services\AiCreditRequestService::GYM_MAX }}" class="input">
                        </x-ui.field>
                        <x-ui.field label="Note (optional)" name="poolNote">
                            <input wire:model="poolNote" type="text" maxlength="300" class="input" placeholder="e.g. Transformation challenge this month">
                        </x-ui.field>
                        <button type="submit" wire:loading.attr="disabled" wire:target="requestPoolCredits" class="btn-primary">Send request</button>
                    </form>
                @endif

                @foreach ($poolRequests->reject(fn ($r) => $r->isPending()) as $past)
                    <p wire:key="pr-{{ $past->id }}" class="flex items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm">
                        <span class="text-gray-500">{{ $past->created_at->format('d M') }} · {{ $past->credits }} credits</span>
                        @if ($past->status === \App\Models\AiCreditRequest::APPROVED)
                            <x-ui.badge color="green">{{ $past->granted_credits }} added</x-ui.badge>
                        @else
                            <x-ui.badge color="red">Declined</x-ui.badge>
                        @endif
                    </p>
                @endforeach
            </div>
        </div>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Latest AI reports</h2></div>
            <table class="table">
                <thead><tr><th>Member</th><th>Type</th><th>Status</th><th>Credits</th><th>Requested</th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr wire:key="r-{{ $report->id }}">
                            <td>
                                @can('view', $report->member)
                                    <a href="{{ route('gym.members.show', ['member' => $report->member, 'tab' => 'ai']) }}" class="font-medium text-indigo-600 hover:underline">{{ $report->member->name }}</a>
                                @else
                                    {{ $report->member->name }}
                                @endcan
                            </td>
                            <td>{{ \App\Models\AiReport::TYPES[$report->type] }}</td>
                            <td><x-ui.badge :color="['completed' => 'green', 'failed' => 'red'][$report->status] ?? 'amber'">{{ ucfirst($report->status) }}</x-ui.badge></td>
                            <td>{{ $report->status === 'failed' ? '0' : $report->credits }}</td>
                            <td class="text-gray-500">{{ $report->created_at->format('d M, h:i A') }} · {{ $report->requested_by }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty message="No AI reports yet." icon="sparkles" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
