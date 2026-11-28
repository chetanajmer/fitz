<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlanDuration;
use App\Exceptions\ApiException;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Membership plans for the staff app (permission plans.manage). Same rules as the web Plans page:
 * deleting archives the plan (soft delete) and never touches memberships already sold.
 */
class PlanController extends AdminController
{
    /** GET /admin/plans?status=active|inactive */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['active', 'inactive'])]]);

        $plans = MembershipPlan::withCount(['memberships as active_members' => fn ($q) => $q->valid()])
            ->when(($data['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($data['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')->orderBy('price')
            ->get();

        return $this->success([
            'plans' => $plans->map(fn (MembershipPlan $plan) => $this->planData($plan))->values(),
            'duration_types' => collect(PlanDuration::cases())->map(fn (PlanDuration $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'days' => $type === PlanDuration::Custom ? null : $type->approxDays(),
            ])->values(),
        ], 'Plans.');
    }

    /** GET /admin/plans/{id} */
    public function show(int $planId): JsonResponse
    {
        return $this->success(['plan' => $this->planData($this->findPlan($planId))], 'Plan.');
    }

    /** POST /admin/plans */
    public function store(Request $request): JsonResponse
    {
        $plan = MembershipPlan::create($this->attributes($this->validated($request, null)) + ['is_active' => true, 'is_public' => true, 'sort_order' => 0]);

        return $this->success(['plan' => $this->planData($this->findPlan($plan->id))], 'Plan saved.', 201);
    }

    /** PUT /admin/plans/{id} — partial: only the fields sent change. */
    public function update(Request $request, int $planId): JsonResponse
    {
        $plan = $this->findPlan($planId);
        $plan->update($this->attributes($this->validated($request, $plan)));

        return $this->success(['plan' => $this->planData($this->findPlan($plan->id))], 'Plan saved.');
    }

    /** POST /admin/plans/{id}/toggle { is_active? } — switch the plan on / off (off: cannot be sold, existing memberships stay). */
    public function toggle(Request $request, int $planId): JsonResponse
    {
        $plan = $this->findPlan($planId);
        $data = $request->validate(['is_active' => ['nullable', 'boolean']]);
        $plan->update(['is_active' => array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : ! $plan->is_active]);

        return $this->success(['plan' => $this->planData($this->findPlan($plan->id))], $plan->is_active ? 'Plan switched on.' : 'Plan switched off.');
    }

    /** DELETE /admin/plans/{id} — archives the plan. */
    public function destroy(int $planId): JsonResponse
    {
        $this->findPlan($planId)->delete();

        return $this->success(null, 'Plan archived. Existing memberships are unaffected.');
    }

    // ---- Helpers ----------------------------------------------------------------

    protected function findPlan(int $planId): MembershipPlan
    {
        return MembershipPlan::withCount(['memberships as active_members' => fn ($q) => $q->valid()])->find($planId)
            ?? throw new ApiException('Plan not found.', 'NOT_FOUND', 404);
    }

    /**
     * Create: everything the web form asks for. Update: the same rules, but only for the fields sent.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?MembershipPlan $plan): array
    {
        $required = $plan ? 'sometimes' : 'required';

        // Features may come as a list or as text with one feature per line (like the web form).
        if (is_string($request->input('features'))) {
            $request->merge(['features' => preg_split('/\r?\n/', $request->input('features'))]);
        }

        $type = $request->input('duration_type', $plan?->duration_type->value);

        $data = $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'duration_type' => [$required, Rule::enum(PlanDuration::class)],
            'duration_days' => [Rule::requiredIf($type === PlanDuration::Custom->value && ! ($plan && $plan->duration_type === PlanDuration::Custom)), 'nullable', 'integer', 'min:1', 'max:1825'],
            'price' => [$required, 'numeric', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:500'],
            'features' => ['nullable', 'array', 'max:30'],
            'features.*' => ['nullable', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['duration_days' => 'duration (days)']);

        return $data + ['_type' => $type];
    }

    /**
     * Same conversion as the web form: custom plans keep their day count, the others use the type's days.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        $attributes = [];
        $type = PlanDuration::from($data['_type']);

        foreach (['name', 'price'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('duration_type', $data) || array_key_exists('duration_days', $data)) {
            $attributes['duration_type'] = $type;
            $attributes['duration_days'] = $type === PlanDuration::Custom ? (int) ($data['duration_days'] ?? 0) : $type->approxDays();

            if ($type === PlanDuration::Custom && empty($data['duration_days'])) {
                unset($attributes['duration_days']);
            }
        }

        if (array_key_exists('description', $data)) {
            $attributes['description'] = $data['description'] ?: null;
        }

        if (array_key_exists('features', $data)) {
            $attributes['features'] = array_values(array_filter(array_map(fn ($f) => trim((string) $f), $data['features'] ?? [])));
        }

        foreach (['is_active', 'is_public'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $attributes[$flag] = (bool) $data[$flag];
            }
        }

        if (array_key_exists('sort_order', $data)) {
            $attributes['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    protected function planData(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'duration_type' => $plan->duration_type->value,
            'duration_days' => (int) $plan->duration_days,
            'duration_label' => $plan->durationLabel(),
            'price' => (float) $plan->price,
            'description' => $plan->description,
            'features' => $plan->features ?? [],
            'is_active' => (bool) $plan->is_active,
            'is_public' => (bool) $plan->is_public,
            'sort_order' => (int) $plan->sort_order,
            'active_members' => (int) ($plan->active_members ?? 0),
        ];
    }
}
