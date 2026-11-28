<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\EnquiryService;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Enquiries (leads) and join requests for the staff app (permission enquiries.manage), through the same
 * EnquiryService and MemberService as the web Enquiries pages.
 *  - Converting an enquiry needs members.manage; the plan part needs memberships.manage, the payment part payments.manage.
 *  - Join requests (website / app sign-ups waiting for approval) need members.manage.
 */
class EnquiryController extends AdminController
{
    public function __construct(protected EnquiryService $enquiries) {}

    /** GET /admin/enquiries?search=&status=&source=&follow_up=today|overdue|due|upcoming&page=&per_page= (due = today or overdue) */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::enum(EnquiryStatus::class)],
            'source' => ['nullable', Rule::enum(EnquirySource::class)],
            'follow_up' => ['nullable', Rule::in(['today', 'overdue', 'due', 'upcoming'])],
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $today = Carbon::today()->toDateString();

        $page = Enquiry::query()
            ->with('interestedPlan')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when(($data['follow_up'] ?? null) === 'today', fn ($q) => $q->open()->whereDate('follow_up_date', $today))
            ->when(($data['follow_up'] ?? null) === 'overdue', fn ($q) => $q->open()->whereDate('follow_up_date', '<', $today))
            ->when(($data['follow_up'] ?? null) === 'due', fn ($q) => $q->open()->whereDate('follow_up_date', '<=', $today))
            ->when(($data['follow_up'] ?? null) === 'upcoming', fn ($q) => $q->open()->whereDate('follow_up_date', '>', $today))
            ->latest()->latest('id')
            ->paginate($this->perPage($request));

        $counts = Enquiry::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $user = $this->staff($request);

        return $this->success([
            'counts' => collect(EnquiryStatus::cases())->mapWithKeys(fn (EnquiryStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all()
                + ['all' => (int) $counts->sum(), 'follow_up_due' => Enquiry::open()->whereDate('follow_up_date', '<=', $today)->count()],
            'join_requests' => $user->can('members.manage') ? $this->joinRequestQuery($request)->count() : null,
            'enquiries' => collect($page->items())->map(fn (Enquiry $e) => $this->enquiryData($e))->values(),
            'pagination' => $this->pagination($page),
        ], 'Enquiries.');
    }

    /** GET /admin/enquiries/options — statuses, sources and plans for the forms. */
    public function options(): JsonResponse
    {
        return $this->success([
            'statuses' => collect(EnquiryStatus::cases())->map(fn (EnquiryStatus $s) => [
                'value' => $s->value, 'label' => $s->label(), 'color' => $s->color(), 'manual' => $s !== EnquiryStatus::Converted,
            ])->values(),
            'sources' => collect(EnquirySource::cases())->map(fn (EnquirySource $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'plans' => MembershipPlan::active()->orderBy('price')->get(['id', 'name', 'price'])
                ->map(fn (MembershipPlan $p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price])->values(),
        ], 'Enquiry options.');
    }

    /** POST /admin/enquiries */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + ['follow_up_date' => ['nullable', 'date']]);
        $enquiry = $this->enquiries->create($this->blankToNull($data) + ['source' => EnquirySource::WalkIn->value]);

        return $this->success($this->detail($request, $enquiry), "Enquiry from {$enquiry->name} added.", 201);
    }

    /** GET /admin/enquiries/{id} — details, activity timeline and members with the same phone. */
    public function show(Request $request, int $enquiryId): JsonResponse
    {
        return $this->success($this->detail($request, $this->findEnquiry($enquiryId)), 'Enquiry.');
    }

    /** PUT /admin/enquiries/{id} — partial: name, phone, email, fitness_goal, interested_plan_id, source, notes. */
    public function update(Request $request, int $enquiryId): JsonResponse
    {
        $enquiry = $this->findEnquiry($enquiryId);
        $rules = collect($this->rules())->map(fn ($rule, $field) => in_array($field, ['name', 'phone', 'source'], true) ? array_merge(['sometimes'], $rule) : $rule)->all();
        $data = $this->blankToNull($request->validate($rules));

        if (! array_key_exists('interested_plan_id', $data)) {
            $data['interested_plan_id'] = $enquiry->interested_plan_id;
        }

        $this->enquiries->update($enquiry, $data);

        return $this->success($this->detail($request, $enquiry->fresh()), 'Enquiry details saved.');
    }

    /** POST /admin/enquiries/{id}/status { status, follow_up_date?, note? } — "converted" only through /convert. */
    public function status(Request $request, int $enquiryId): JsonResponse
    {
        $enquiry = $this->findEnquiry($enquiryId);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(EnquiryStatus::manualOptions()))],
            'follow_up_date' => ['nullable', 'required_if:status,'.EnquiryStatus::FollowUp->value, 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['follow_up_date.required_if' => 'Choose the follow-up date.']);

        if ($enquiry->isConverted()) {
            throw new ApiException('Converted enquiries cannot change status.', 'ALREADY_CONVERTED', 409);
        }

        $this->enquiries->changeStatus(
            $enquiry,
            EnquiryStatus::from($data['status']),
            $data['note'] ?? null,
            $request->has('follow_up_date') ? (string) ($data['follow_up_date'] ?? '') : null,
        );

        return $this->success($this->detail($request, $enquiry->fresh()), 'Enquiry updated.');
    }

    /** POST /admin/enquiries/{id}/notes { note } */
    public function addNote(Request $request, int $enquiryId): JsonResponse
    {
        $enquiry = $this->findEnquiry($enquiryId);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->enquiries->addNote($enquiry, $data['note']);

        return $this->success($this->detail($request, $enquiry->fresh()), 'Note added.', 201);
    }

    /**
     * POST /admin/enquiries/{id}/convert — make the enquiry a member, optionally with a plan and a payment.
     * Returns 201 with { member_id, member_code, enquiry }.
     */
    public function convert(Request $request, int $enquiryId): JsonResponse
    {
        $user = $this->staff($request);

        if (! $user->can('members.manage')) {
            throw $this->forbidden('You cannot register members.');
        }

        $enquiry = $this->findEnquiry($enquiryId);

        if ($enquiry->isConverted()) {
            throw new ApiException('This enquiry has already been converted to a member.', 'ALREADY_CONVERTED', 409, ['member_id' => $enquiry->converted_member_id]);
        }

        $data = $request->validate([
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'joining_date' => ['nullable', 'date'],
            'trainer_id' => ['nullable', 'integer'],
            'plan_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'pay_now' => ['sometimes', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], ['start_date' => 'start date']);

        $planId = ! empty($data['plan_id']) ? (int) $data['plan_id'] : null;
        $collect = $planId && $request->boolean('pay_now') && (float) ($data['amount'] ?? 0) > 0;

        if ($planId && ! $user->can('memberships.manage')) {
            throw $this->forbidden('You cannot assign plans.');
        }

        if ($collect && ! $user->can('payments.manage')) {
            throw $this->forbidden('You cannot collect payments.');
        }

        if ($planId && ! MembershipPlan::active()->whereKey($planId)->exists()) {
            throw new ApiException('Plan not found.', 'NOT_FOUND', 404);
        }

        if ($collect && empty($data['method'])) {
            $request->validate(['method' => ['required']]);
        }

        $member = $this->enquiries->convert($enquiry, array_filter([
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'joining_date' => $data['joining_date'] ?? now()->toDateString(),
            'status' => 'active',
        ]), [
            // Trainers limited to their own members become the new member's trainer.
            'trainer_id' => $user->can('members.all') ? (! empty($data['trainer_id']) ? (int) $data['trainer_id'] : null) : $user->id,
            'plan_id' => $planId,
            'start_date' => $planId ? ($data['start_date'] ?? now()->toDateString()) : null,
            'discount' => (float) ($data['discount'] ?? 0),
            'payment' => $collect ? ['amount' => (float) $data['amount'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null] : null,
        ]);

        return $this->success([
            'member_id' => $member->id,
            'member_code' => $member->member_code,
            'enquiry' => $this->detail($request, $enquiry->fresh())['enquiry'],
        ], "{$member->name} is now member {$member->member_code}.", 201);
    }

    // ---- Join requests --------------------------------------------------------------

    /** GET /admin/join-requests — website / app sign-ups waiting for approval, oldest first. */
    public function joinRequests(Request $request): JsonResponse
    {
        $this->requireMemberManager($request);

        return $this->success([
            'join_requests' => $this->joinRequestQuery($request)->oldest()->oldest('id')->get()->map(fn (Member $m) => $this->memberBrief($m) + [
                'email' => $m->email,
                'gender' => $m->gender,
                'fitness_goal' => $m->fitness_goal,
                'requested_at' => $this->iso($m->created_at),
            ])->values(),
        ], 'Join requests.');
    }

    /** POST /admin/join-requests/{memberId}/approve — the member can log in to the app from now on. */
    public function approveJoinRequest(Request $request, int $memberId, MemberService $members): JsonResponse
    {
        $member = $this->pendingMember($request, $memberId);
        $members->approveRegistration($member);

        return $this->success(['member_id' => $member->id, 'member_code' => $member->member_code], "{$member->name} approved. They can now log in to the app.");
    }

    /** POST /admin/join-requests/{memberId}/decline — removed; the person can apply again later. */
    public function declineJoinRequest(Request $request, int $memberId, MemberService $members): JsonResponse
    {
        $member = $this->pendingMember($request, $memberId);
        $members->declineRegistration($member);

        return $this->success(null, "Join request from {$member->name} declined.");
    }

    // ---- Helpers ----------------------------------------------------------------

    protected function findEnquiry(int $enquiryId): Enquiry
    {
        return Enquiry::find($enquiryId) ?? throw new ApiException('Enquiry not found.', 'NOT_FOUND', 404);
    }

    protected function requireMemberManager(Request $request): void
    {
        if (! $this->staff($request)->can('members.manage')) {
            throw $this->forbidden('You cannot approve members.');
        }
    }

    protected function joinRequestQuery(Request $request)
    {
        return Member::visibleTo($this->staff($request))->where('status', MemberStatus::Pending->value);
    }

    protected function pendingMember(Request $request, int $memberId): Member
    {
        $this->requireMemberManager($request);
        $member = $this->visibleMemberOr404($request, $memberId);

        if ($member->status !== MemberStatus::Pending) {
            throw new ApiException("{$member->name} is not waiting for approval.", 'NOT_PENDING', 409);
        }

        return $member;
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'fitness_goal' => ['nullable', 'string', 'max:255'],
            'interested_plan_id' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::enum(EnquirySource::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function blankToNull(array $data): array
    {
        return array_filter(array_map(fn ($v) => $v === '' ? null : $v, $data), fn ($v, $k) => $v !== null || $k !== 'source', ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, mixed> */
    protected function enquiryData(Enquiry $enquiry): array
    {
        return [
            'id' => $enquiry->id,
            'name' => $enquiry->name,
            'phone' => $enquiry->phone,
            'email' => $enquiry->email,
            'fitness_goal' => $enquiry->fitness_goal,
            'status' => $enquiry->status->value,
            'status_label' => $enquiry->status->label(),
            'source' => $enquiry->source->value,
            'source_label' => $enquiry->source->label(),
            'interested_plan' => $enquiry->interestedPlan ? ['id' => $enquiry->interestedPlan->id, 'name' => $enquiry->interestedPlan->name] : null,
            'follow_up_date' => $enquiry->follow_up_date?->toDateString(),
            'follow_up_due' => $enquiry->follow_up_date !== null && ! $enquiry->isConverted()
                && $enquiry->status !== EnquiryStatus::NotInterested && $enquiry->follow_up_date->lte(Carbon::today()),
            'converted_member_id' => $enquiry->converted_member_id,
            'created_at' => $this->iso($enquiry->created_at),
        ];
    }

    /** @return array<string, mixed> */
    protected function detail(Request $request, Enquiry $enquiry): array
    {
        $user = $this->staff($request);
        $enquiry->loadMissing(['interestedPlan', 'convertedMember', 'activities.user']);

        return [
            'enquiry' => $this->enquiryData($enquiry) + [
                'notes' => $enquiry->notes,
                'converted_at' => $this->iso($enquiry->converted_at),
                'converted_member' => $enquiry->convertedMember ? $this->memberBrief($enquiry->convertedMember) : null,
            ],
            // Newest first. type: created | status_changed | note | converted.
            'timeline' => $enquiry->activities->map(fn (EnquiryActivity $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'text' => $a->note,
                'from_status' => $a->from_status,
                'to_status' => $a->to_status,
                'by' => $this->person($a->user),
                'at' => $this->iso($a->created_at),
            ])->values(),
            'duplicates' => Member::where('phone', $enquiry->phone)->get()->map(fn (Member $m) => $this->memberBrief($m))->values(),
            'can' => [
                'convert' => ! $enquiry->isConverted() && $user->can('members.manage'),
                'assign_trainer' => $user->can('members.all'),
                'assign_plan' => $user->can('memberships.manage'),
                'collect_payment' => $user->can('payments.manage'),
            ],
        ];
    }
}
