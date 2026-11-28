<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DietPreference;
use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\MemberProgram;
use App\Models\Membership;
use App\Models\Payment;
use App\Services\MemberService;
use App\Services\StreakService;
use App\Services\WeightTrackingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Members in the staff app: /api/v1/admin/members*. Trainers without "members.all" only see and change
 * the members assigned to them; anyone else answers 404, as in the web panel.
 */
class MemberController extends AdminController
{
    /** Member form fields (the same as the web panel form). */
    protected const FIELDS = [
        'name', 'phone', 'email', 'gender', 'date_of_birth', 'blood_group', 'height_cm', 'address', 'city',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'joining_date', 'fitness_goal', 'diet_preference', 'medical_notes', 'notes', 'status',
    ];

    public function __construct(protected MemberService $members) {}

    /** GET /admin/members?search=&status=&membership=&trainer_id=&sort=recent|name|expiring&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(MemberStatus::class)],
            'membership' => ['nullable', 'in:active,expired,upcoming,none'],
            'sort' => ['nullable', 'in:recent,name,expiring'],
        ]);

        $user = $this->staff($request);
        $today = Carbon::today()->toDateString();
        $membership = (string) $request->input('membership');
        $trainer = (string) $request->input('trainer_id');

        $query = Member::query()
            ->visibleTo($user)
            ->select('members.*')
            ->selectSub(Attendance::selectRaw('MAX(check_in_at)')->whereColumn('attendances.member_id', 'members.id'), 'last_check_in_at')
            ->with(['latestMembership', 'trainer'])
            ->search($request->input('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($membership === 'active', fn ($q) => $q->withValidMembership())
            ->when($membership === 'expired', fn ($q) => $q->expired())
            ->when($membership === 'upcoming', fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where('status', '!=', 'cancelled')->whereDate('start_date', '>', $today))->whereDoesntHave('memberships', fn ($m) => $m->valid()))
            ->when($membership === 'none', fn ($q) => $q->whereDoesntHave('memberships', fn ($m) => $m->where('status', '!=', 'cancelled')))
            ->when($trainer !== '' && $user->can('members.all'), fn ($q) => $trainer === 'none' ? $q->whereNull('trainer_id') : $q->where('trainer_id', (int) $trainer));

        $this->sort($query, (string) $request->input('sort', 'recent'), $today);

        $page = $query->paginate($this->perPage($request));
        $checkedIn = Attendance::whereDate('date', $today)->whereIn('member_id', collect($page->items())->pluck('id'))->pluck('member_id')->flip();

        return $this->success([
            'members' => collect($page->items())->map(fn (Member $m) => $this->memberBrief($m) + [
                'status' => $m->status->value,
                'trainer' => $this->person($m->trainer),
                'membership' => $m->latestMembership ? Arr::only($this->membershipData($m->latestMembership), ['id', 'plan_name', 'start_date', 'end_date', 'status', 'days_left', 'balance']) : null,
                'last_check_in_at' => $m->last_check_in_at ? Carbon::parse($m->last_check_in_at)->toIso8601String() : null,
                'checked_in_today' => $checkedIn->has($m->id),
            ])->all(),
            'pagination' => $this->pagination($page),
        ], 'Members.');
    }

    /** GET /admin/members/{id} */
    public function show(Request $request, int $memberId): JsonResponse
    {
        return $this->success($this->detail($request, $this->findMember($request, $memberId)), 'Member.');
    }

    /** POST /admin/members (multipart when a photo is sent). Duplicate phone: 422 PHONE_TAKEN with data.member_id. */
    public function store(Request $request): JsonResponse
    {
        $user = $this->staff($request);

        if (! $user->can('create', Member::class)) {
            throw $this->forbidden();
        }

        $data = $request->validate($this->rules() + ['photo' => ['nullable', 'image', 'max:2048']]);
        $this->ensurePhoneFree($data['phone']);

        $extra = ['trainer_id' => $user->can('members.all') ? (isset($data['trainer_id']) ? (int) $data['trainer_id'] : null) : $user->id];
        $member = $this->members->create($this->attributes($data), $extra, $request->file('photo'));

        return $this->success($this->detail($request, $member->fresh()), "Member {$member->member_code} created. Assign a plan to activate their membership.", 201);
    }

    /** PUT /admin/members/{id} — the same fields as create; the photo has its own endpoint. */
    public function update(Request $request, int $memberId): JsonResponse
    {
        $user = $this->staff($request);
        $member = $this->findMember($request, $memberId);

        if (! $user->can('update', $member)) {
            throw $this->forbidden();
        }

        $data = $request->validate($this->rules());
        $this->ensurePhoneFree($data['phone'], $member);

        // A trainer limited to assigned members cannot hand members to someone else.
        $extra = $user->can('members.all') && array_key_exists('trainer_id', $data) ? ['trainer_id' => isset($data['trainer_id']) ? (int) $data['trainer_id'] : null] : [];
        $this->members->update($member, $this->attributes($data), $extra);

        return $this->success($this->detail($request, $member->fresh()), 'Member updated.');
    }

    /** POST /admin/members/{id}/photo { photo } */
    public function uploadPhoto(Request $request, int $memberId): JsonResponse
    {
        $member = $this->editableMember($request, $memberId);
        $request->validate(['photo' => ['required', 'image', 'max:2048']]);

        $this->members->update($member, [], [], $request->file('photo'));

        return $this->success(['photo_url' => $member->fresh()->photoUrl()], 'Photo updated.');
    }

    /** DELETE /admin/members/{id}/photo */
    public function deletePhoto(Request $request, int $memberId): JsonResponse
    {
        $member = $this->editableMember($request, $memberId);

        if ($member->photo_path) {
            Storage::disk('public')->delete($member->photo_path);
            $member->photo_path = null;
            $member->save();
        }

        return $this->success(['photo_url' => $member->photoUrl()], 'Photo removed.');
    }

    /** POST /admin/members/{id}/status { status: active|inactive } */
    public function status(Request $request, int $memberId): JsonResponse
    {
        $member = $this->editableMember($request, $memberId);
        $data = $request->validate(['status' => ['required', 'in:active,inactive']]);

        $member->status = MemberStatus::from($data['status']);
        $member->save();

        return $this->success(['id' => $member->id, 'status' => $member->status->value], "Member marked {$member->status->label()}.");
    }

    /**
     * POST /admin/members/{id}/password { password, password_confirmation }
     * The member's website sign-in (their email + this password); created when they have none yet.
     * The member app is not affected: it signs in with an OTP.
     */
    public function password(Request $request, int $memberId): JsonResponse
    {
        $member = $this->editableMember($request, $memberId);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);

        $user = $this->members->setPortalPassword($member, $data['password']);

        return $this->success(['id' => $member->id, 'login_email' => $user->email], "Password set. {$member->name} signs in to the website with {$user->email}.");
    }

    /** POST /admin/members/{id}/trainer { trainer_id|null } — needs "members.all". */
    public function trainer(Request $request, int $memberId): JsonResponse
    {
        $member = $this->editableMember($request, $memberId);

        if (! $this->staff($request)->can('members.all')) {
            throw $this->forbidden();
        }

        $data = $request->validate(['trainer_id' => ['present', 'nullable', 'integer']]);
        $this->members->assignTrainer($member, $data['trainer_id'] ? (int) $data['trainer_id'] : null);

        return $this->success(['id' => $member->id, 'trainer' => $this->person($member->fresh('trainer')->trainer)], 'Trainer updated.');
    }

    /** DELETE /admin/members/{id} — soft delete, gym admins only. */
    public function destroy(Request $request, int $memberId): JsonResponse
    {
        $user = $this->staff($request);
        $member = $this->findMember($request, $memberId);

        if (! $user->isGymAdmin() || ! $user->can('delete', $member)) {
            throw $this->forbidden('Only a gym admin can delete members.');
        }

        $this->members->delete($member);

        return $this->success(null, 'Member deleted.');
    }

    // ---- Helpers ------------------------------------------------------------------------------------

    protected function findMember(Request $request, int $memberId): Member
    {
        return Member::visibleTo($this->staff($request))->findOrFail($memberId);
    }

    protected function editableMember(Request $request, int $memberId): Member
    {
        $member = $this->findMember($request, $memberId);

        if (! $this->staff($request)->can('update', $member)) {
            throw $this->forbidden();
        }

        return $member;
    }

    protected function sort(Builder $query, string $sort, string $today): void
    {
        match ($sort) {
            'name' => $query->orderBy('members.name')->orderBy('members.id'),
            // Soonest end date of a membership that has not ended yet; members without one come last.
            'expiring' => $query
                ->selectSub(Membership::selectRaw('MIN(end_date)')->whereColumn('memberships.member_id', 'members.id')
                    ->where('status', '!=', MembershipStatus::Cancelled->value)->whereDate('end_date', '>=', $today), 'next_end_date')
                ->orderByRaw('next_end_date IS NULL')->orderBy('next_end_date')->orderBy('members.id'),
            default => $query->latest('members.id'),
        };
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'height_cm' => ['nullable', 'numeric', 'between:50,250'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'joining_date' => ['required', 'date'],
            'fitness_goal' => ['nullable', 'string', 'max:255'],
            'diet_preference' => ['nullable', Rule::enum(DietPreference::class)],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'trainer_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, mixed> */
    protected function attributes(array $data): array
    {
        return collect(self::FIELDS)->mapWithKeys(fn (string $field) => [$field => ($data[$field] ?? null) === '' ? null : ($data[$field] ?? null)])->all();
    }

    /** Another (not deleted) member of this gym already uses the number. */
    protected function ensurePhoneFree(string $phone, ?Member $ignore = null): void
    {
        $taken = Member::where('phone_digits', Member::normalizePhone($phone))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->value('id');

        if ($taken) {
            throw new ApiException('Another member of your gym already uses this phone number.', 'PHONE_TAKEN', 422, ['member_id' => $taken]);
        }
    }

    /** @return array<string, mixed> */
    protected function detail(Request $request, Member $member): array
    {
        $user = $this->staff($request);
        $member->loadMissing(['trainer']);
        $today = Carbon::today();
        $current = $member->currentMembership();
        $todayAttendance = Attendance::where('member_id', $member->id)->whereDate('date', $today)->first();
        $streak = app(StreakService::class)->summary($member);
        $weight = app(WeightTrackingService::class);
        $latestWeight = $weight->latestLog($member);
        $goal = $weight->currentGoal($member);
        $canUpdate = $user->can('update', $member);

        $memberships = $member->memberships()->with('payments')->get()->map(fn (Membership $m) => $this->membershipData($m) + [
            'notes' => $m->notes,
            'payments' => $m->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_no' => $p->receipt_number,
                'amount' => (float) $p->amount,
                'method' => $p->method?->value,
                'reference' => $p->reference,
                'paid_at' => $this->iso($p->paid_at),
            ])->all(),
        ]);

        return [
            'member' => $this->memberBrief($member) + [
                'email' => $member->email,
                'gender' => $member->gender,
                'date_of_birth' => $member->date_of_birth?->toDateString(),
                'blood_group' => $member->blood_group,
                'height_cm' => $member->height_cm !== null ? (float) $member->height_cm : null,
                'address' => $member->address,
                'city' => $member->city,
                'emergency_contact_name' => $member->emergency_contact_name,
                'emergency_contact_phone' => $member->emergency_contact_phone,
                'emergency_contact_relation' => $member->emergency_contact_relation,
                'joining_date' => $member->joining_date?->toDateString(),
                'fitness_goal' => $member->fitness_goal,
                'diet_preference' => $member->diet_preference?->value,
                'medical_notes' => $member->medical_notes,
                'notes' => $member->notes,
                'status' => $member->status->value,
                'created_at' => $this->iso($member->created_at),
            ],
            'trainer' => $this->person($member->trainer),
            'current_membership' => $current ? $this->membershipData($current) : null,
            'balance_due' => round($member->pendingAmount(), 2),
            'memberships' => $memberships->all(),
            'attendance_summary' => [
                'this_month' => $member->attendances()->reorder()->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()])->count(),
                'last_30_days' => $member->attendances()->reorder()->where('date', '>=', $today->copy()->subDays(30)->toDateString())->count(),
                'checked_in_today' => (bool) $todayAttendance,
                'today' => $todayAttendance ? $this->attendanceData($todayAttendance, false) : null,
                'current_streak' => $streak['current_streak'],
                'longest_streak' => $streak['longest_streak'],
                'last_visits' => $member->attendances()->limit(10)->get()->map(fn (Attendance $a) => $this->attendanceData($a, false))->all(),
            ],
            'programs' => MemberProgram::with('program')->where('member_id', $member->id)->current()->orderBy('start_date')->get()
                ->map(fn (MemberProgram $p) => [
                    'id' => $p->id,
                    'program_id' => $p->fitness_program_id,
                    'name' => $p->program?->title,
                    'type' => $p->type?->value,
                    'start_date' => $p->start_date->toDateString(),
                    'end_date' => $p->end_date->toDateString(),
                    'status' => $p->state(),
                ])->all(),
            'weight' => [
                'latest' => $latestWeight ? ['kg' => (float) $latestWeight->weight_kg, 'measured_on' => $latestWeight->measured_on->toDateString()] : null,
                'goal' => $goal ? ['target_kg' => (float) $goal->target_weight_kg, 'target_date' => $goal->target_date?->toDateString()] : null,
            ],
            'devices_count' => $member->devices()->count(),
            'can' => [
                'edit' => $canUpdate,
                'delete' => $user->isGymAdmin() && $user->can('delete', $member),
                'check_in' => $user->can('checkIn', $member),
                'assign_plan' => $canUpdate && $user->can('memberships.manage'),
                'collect_payment' => $canUpdate && $user->can('payments.manage'),
                'assign_trainer' => $canUpdate && $user->can('members.all'),
            ],
        ];
    }
}
