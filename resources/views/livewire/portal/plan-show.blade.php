<div>
    <a href="{{ route('portal.plans', $plan->type->value) }}" class="btn-secondary btn-sm mb-4">← {{ $plan->type->pluralLabel() }}</a>
    <x-plan.view :plan="$plan" />
</div>
