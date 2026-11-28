<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Picker lists for staff app forms: /api/v1/admin/options/* */
class OptionsController extends AdminController
{
    /** GET /admin/options/plans — active plans. */
    public function plans(): JsonResponse
    {
        $plans = MembershipPlan::active()->orderBy('sort_order')->orderBy('price')->get()
            ->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'duration_type' => $plan->duration_type->value,
                'duration_days' => (int) $plan->duration_days,
            ])->all();

        return $this->success(['plans' => $plans], 'Plans.');
    }

    /** GET /admin/options/trainers — active trainers. */
    public function trainers(Request $request): JsonResponse
    {
        $trainers = User::trainersOf($this->staff($request)->gym_id)->where('status', 'active')->orderBy('name')->get()
            ->map(fn (User $trainer) => ['id' => $trainer->id, 'name' => $trainer->name, 'photo_url' => $trainer->photoUrl()])->all();

        return $this->success(['trainers' => $trainers], 'Trainers.');
    }
}
