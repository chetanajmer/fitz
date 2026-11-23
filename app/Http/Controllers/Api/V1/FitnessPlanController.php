<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FitnessPlanType;
use App\Exceptions\ApiException;
use App\Http\Resources\V1\FitnessPlanResource;
use App\Models\PlanCategory;
use App\Services\FitnessPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Diet charts & workout plans for the member app.
 * The member sees published default plans of their gym + their own customised plans.
 */
class FitnessPlanController extends ApiController
{
    public function __construct(protected FitnessPlanService $plans) {}

    /**
     * GET /api/v1/me/plans?type=diet|workout
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::enum(FitnessPlanType::class)]]);
        $type = FitnessPlanType::from($data['type']);
        $library = $this->plans->libraryFor($request->user('member'), $type);

        $summaries = fn ($plans) => FitnessPlanResource::collection($plans)->resolve($request);

        return $this->success([
            'type' => $type->value,
            'custom' => $summaries($library['custom']),
            'categories' => $library['categories']->map(fn (PlanCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'image_url' => $c->imageUrl(),
                'plans' => $summaries($c->plans),
            ])->values()->all(),
            'uncategorized' => $summaries($library['uncategorized']),
        ], $type->pluralLabel().'.');
    }

    /**
     * GET /api/v1/me/plans/{id}
     */
    public function show(Request $request, int $planId): JsonResponse
    {
        $plan = $this->plans->findForMember($request->user('member'), $planId);

        if (! $plan) {
            throw new ApiException('Plan not found.', 'PLAN_NOT_FOUND', 404);
        }

        return $this->success((new FitnessPlanResource($plan))->withSections()->resolve($request), $plan->type->label().'.');
    }
}
