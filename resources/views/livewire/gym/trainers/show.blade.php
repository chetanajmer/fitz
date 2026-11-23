<div>
    <div class="card card-body mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <img src="{{ $trainer->photoUrl() }}" alt="" class="size-16 rounded-full object-cover">
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-semibold">{{ $trainer->name }}</h1>
                <x-ui.badge :color="$trainer->status === 'active' ? 'green' : 'gray'">{{ ucfirst($trainer->status) }}</x-ui.badge>
            </div>
            <p class="text-sm text-gray-500">{{ $trainer->specialization ?: 'Trainer' }} · {{ $trainer->email }} @if($trainer->phone)· {{ $trainer->phone }}@endif</p>
            @if ($trainer->bio)<p class="mt-1 text-sm text-gray-600">{{ $trainer->bio }}</p>@endif
            <p class="mt-1 text-xs text-gray-400">Last login: {{ $trainer->last_login_at?->diffForHumans() ?? 'never' }}</p>
        </div>
        <a href="{{ route('gym.trainers.index') }}" class="btn-secondary">All trainers</a>
    </div>

    <form id="access" wire:submit="saveAccess" class="card mb-6 scroll-mt-20">
        <div class="card-header">
            <div>
                <h2 class="card-title">Sections this trainer can see</h2>
                <p class="text-xs text-gray-500">The dashboard is always available. Unticked sections disappear from their menu and are blocked.</p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($presets as $key => $preset)
                    <button type="button" wire:click="applyPreset('{{ $key }}')" class="btn-ghost btn-sm">{{ $preset['label'] }}</button>
                @endforeach
            </div>
        </div>
        <div class="grid gap-6 p-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($sections as $group => $items)
                <fieldset>
                    <legend class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">{{ $group }}</legend>
                    <div class="space-y-3">
                        @foreach ($items as $permission => $item)
                            <label wire:key="perm-{{ $permission }}" class="flex cursor-pointer items-start gap-2.5">
                                <input type="checkbox" wire:model="access" value="{{ $permission }}" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                                <span><span class="block text-sm font-medium">{{ $item['label'] }}</span><span class="block text-xs text-gray-500">{{ $item['hint'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-5 py-3">
            <p class="text-xs text-gray-500">Gym profile, Gymai billing and trainer management stay with gym admins.</p>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveAccess">Save access</button>
        </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <div id="members" class="card scroll-mt-20">
            <div class="card-header"><h2 class="card-title">Assigned members ({{ $members->count() }})</h2></div>
            <ul class="divide-y divide-gray-100">
                @forelse ($members as $m)
                    <li wire:key="am-{{ $m->id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                        <a href="{{ route('gym.members.show', $m) }}" class="flex min-w-0 items-center gap-3">
                            <img src="{{ $m->photoUrl() }}" alt="" class="size-8 rounded-full object-cover">
                            <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $m->name }}</p><p class="text-xs text-gray-500">{{ $m->member_code }} · {{ ucfirst($m->membershipState()) }}</p></div>
                        </a>
                        <button wire:click="unassign({{ $m->id }})" class="btn-ghost btn-sm text-red-600">Remove</button>
                    </li>
                @empty
                    <li><x-ui.empty message="No members assigned yet." icon="users" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Assign members</h2>
                <button wire:click="assignSelected" class="btn-primary btn-sm" @disabled(empty($selected))>Assign {{ count($selected) ?: '' }}</button>
            </div>
            <div class="border-b border-gray-100 p-4">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search active members…" class="input">
            </div>
            <ul class="max-h-[28rem] divide-y divide-gray-100 overflow-y-auto">
                @forelse ($candidates as $c)
                    <li wire:key="c-{{ $c->id }}">
                        <label class="flex cursor-pointer items-center gap-3 px-5 py-2.5 hover:bg-gray-50">
                            <input type="checkbox" wire:model.live="selected" value="{{ $c->id }}" class="rounded border-gray-300 text-indigo-600">
                            <span class="flex-1 text-sm">{{ $c->name }} <span class="text-xs text-gray-500">{{ $c->member_code }}</span></span>
                            @if ($c->trainer)<span class="text-xs text-gray-400">with {{ $c->trainer->name }}</span>@endif
                        </label>
                    </li>
                @empty
                    <li><x-ui.empty message="No members found." icon="users" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
