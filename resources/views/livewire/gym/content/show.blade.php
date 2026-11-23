<div class="mx-auto max-w-4xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('gym.content.index', ['type' => $p->type->value, 'tab' => $p->isCustom() ? 'custom' : 'defaults']) }}" class="btn-secondary btn-sm">← {{ $p->type->pluralLabel() }}</a>
        <div class="flex flex-wrap items-center gap-2">
            @unless ($p->is_published)<x-ui.badge color="amber">Draft — not visible to members</x-ui.badge>@endunless
            @if ($p->member)
                <a href="{{ route('gym.members.show', [$p->member_id, 'tab' => 'plans']) }}" class="btn-ghost btn-sm">View {{ $p->member->name }}</a>
            @endif
            @can('update', $p)
                <a href="{{ route('gym.content.edit', $p) }}" class="btn-primary btn-sm">Edit</a>
            @endcan
        </div>
    </div>

    <x-plan.view :plan="$p" />

    <p class="mt-3 text-xs text-gray-500">
        {{ $p->isCustom() ? 'Visible only to '.$p->member?->name.'.' : 'Visible to all members.' }}
        Created by {{ $p->creator?->name ?? '—' }} · last updated {{ $p->updated_at->diffForHumans() }}.
    </p>
</div>
