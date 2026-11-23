<?php

namespace App\Services;

use App\Enums\FitnessPlanType;
use App\Models\Attendance;
use App\Models\FitnessPlan;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Everything the member app's profile screen can show, in one payload.
 * Must run inside the member's gym tenant context. Staff-only notes are never included.
 */
class MemberProfileService
{
    /** Fields a member may change from the app. Phone (their login) and name are managed by the gym. */
    public const EDITABLE_FIELDS = [
        'email', 'gender', 'date_of_birth', 'blood_group', 'height_cm', 'address', 'city',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'fitness_goal', 'diet_preference', 'medical_notes',
    ];

    public function __construct(
        protected MemberMembershipService $memberships,
        protected StreakService $streaks,
        protected AttendanceService $attendance,
        protected WeightTrackingService $weights,
    ) {}

    /** @return array<string, mixed> */
    public function full(Member $member): array
    {
        $member->loadMissing(['gym', 'trainer']);
        $gym = $member->gym;
        $today = Carbon::today();
        $streak = $this->streaks->summary($member);
        $todayVisit = $this->attendance->today($member);
        $lastVisit = Attendance::where('member_id', $member->id)->latest('check_in_at')->first();

        return [
            'personal' => [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'name' => $member->name,
                'phone' => $member->phone,
                'email' => $member->email,
                'gender' => $member->gender,
                'date_of_birth' => $member->date_of_birth?->toDateString(),
                'age' => $member->date_of_birth?->age,
                'blood_group' => $member->blood_group,
                'height_cm' => $member->height_cm !== null ? (float) $member->height_cm : null,
                'photo_url' => $member->photoUrl(),
                'has_photo' => (bool) $member->photo_path,
            ],
            'address' => [
                'address' => $member->address,
                'city' => $member->city,
            ],
            'emergency_contact' => [
                'name' => $member->emergency_contact_name,
                'phone' => $member->emergency_contact_phone,
                'relation' => $member->emergency_contact_relation,
            ],
            'health' => [
                'fitness_goal' => $member->fitness_goal,
                'diet_preference' => $member->diet_preference?->value,
                'diet_preference_label' => $member->diet_preference?->label(),
                'medical_notes' => $member->medical_notes,
            ],
            'account' => [
                'status' => $member->status->value,
                'joining_date' => $member->joining_date?->toDateString(),
                'member_since_days' => $member->joining_date ? (int) $member->joining_date->diffInDays($today) : null,
                'has_web_portal_login' => (bool) $member->user_id,
            ],
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'logo_url' => $gym->logoUrl(),
                'phone' => $gym->phone,
                'email' => $gym->email,
                'whatsapp' => $gym->allSettings()['social']['whatsapp'] ?? null,
                'address' => $gym->address,
                'city' => $gym->city,
                'primary_color' => $gym->primaryColor(),
            ],
            'trainer' => $member->trainer ? [
                'id' => $member->trainer->id,
                'name' => $member->trainer->name,
                'phone' => $member->trainer->phone,
                'email' => $member->trainer->email,
                'specialization' => $member->trainer->specialization,
                'bio' => $member->trainer->bio,
                'photo_url' => $member->trainer->photoUrl(),
            ] : null,
            'membership' => $this->memberships->card($member),
            'membership_history' => $this->memberships->history($member),
            'payments' => $this->payments($member),
            'attendance' => [
                'total_visits' => Attendance::where('member_id', $member->id)->count(),
                'visits_this_month' => Attendance::where('member_id', $member->id)->whereBetween('date', [$today->copy()->startOfMonth(), $today])->count(),
                'visits_last_30_days' => Attendance::where('member_id', $member->id)->where('date', '>=', $today->copy()->subDays(29))->count(),
                'last_visit_at' => $lastVisit?->check_in_at?->toIso8601String(),
                'checked_in_today' => (bool) $todayVisit,
                'today' => $todayVisit ? [
                    'check_in_at' => $todayVisit->check_in_at?->toIso8601String(),
                    'check_out_at' => $todayVisit->check_out_at?->toIso8601String(),
                ] : null,
            ],
            'streak' => [
                'current_streak' => $streak['current_streak'],
                'longest_streak' => $streak['longest_streak'],
                'total_visit_days' => $streak['total_visit_days'],
                'highest_milestone' => $streak['highest_milestone'],
                'next_milestone' => $streak['next_milestone'],
            ],
            'weight' => collect($this->weights->summary($member, 12))->except('history')->all(),
            'plans' => [
                'diet' => FitnessPlan::visibleToMember($member)->where('type', FitnessPlanType::Diet->value)->count(),
                'workout' => FitnessPlan::visibleToMember($member)->where('type', FitnessPlanType::Workout->value)->count(),
                'custom_for_me' => FitnessPlan::visibleToMember($member)->where('member_id', $member->id)->count(),
            ],
            'check_in' => [
                // Render this string as a QR code in the app; staff scan it at the front desk.
                'qr_payload' => route('gym.attendance.scan', $member->qr_token),
                'self_check_in_enabled' => $gym->allowsSelfCheckIn(),
            ],
            'editable_fields' => self::EDITABLE_FIELDS,
        ];
    }

    /** @return array<string, mixed> */
    protected function payments(Member $member): array
    {
        $recent = Payment::where('member_id', $member->id)->where('status', 'completed')
            ->with('membership:id,plan_name')->latest('paid_at')->limit(10)->get();

        return [
            'total_paid' => (float) Payment::where('member_id', $member->id)->where('status', 'completed')->sum('amount'),
            'pending_amount' => round($member->pendingAmount(), 2),
            'count' => Payment::where('member_id', $member->id)->where('status', 'completed')->count(),
            'recent' => $recent->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'amount' => (float) $p->amount,
                'method' => $p->method->value,
                'method_label' => $p->method->label(),
                'reference' => $p->reference,
                'paid_at' => $p->paid_at->toIso8601String(),
                'plan_name' => $p->membership?->plan_name,
            ])->all(),
        ];
    }
}
