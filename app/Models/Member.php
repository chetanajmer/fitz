<?php

namespace App\Models;

use App\Enums\DietPreference;
use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Members authenticate to the mobile app with Sanctum tokens (guard: "member").
 */
class Member extends Model implements AuthenticatableContract
{
    use Authenticatable, BelongsToGym, HasApiTokens, SoftDeletes;

    protected $hidden = ['qr_token', 'medical_notes', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (Member $member) {
            if ($member->isDirty('phone') || $member->phone_digits === null) {
                $member->phone_digits = static::normalizePhone($member->phone);
            }
        });
    }

    /** Last 10 digits of a phone number (Indian mobile numbers, with or without +91 / 0 prefixes). */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : substr($digits, -10);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(MemberOtp::class);
    }

    /** Members have no password; the OTP flow is their only credential. */
    public function getAuthPassword(): string
    {
        return '';
    }

    // user_id, trainer_id, enquiry_id, sequence, member_code, qr_token are assigned by MemberService.
    protected $fillable = [
        'name', 'phone', 'email', 'gender', 'date_of_birth', 'blood_group', 'height_cm', 'address', 'city',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'joining_date', 'fitness_goal', 'diet_preference', 'medical_notes', 'notes', 'photo_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'height_cm' => 'decimal:1',
            'diet_preference' => DietPreference::class,
            'notification_settings' => 'array',
            'status' => MemberStatus::class,
            'ai_access' => 'boolean',
            'ai_consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(MemberDevice::class);
    }

    /** The member's in-app notification list (newest first). */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(MemberNotification::class)->latest('id');
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class)->orderByDesc('measured_on');
    }

    public function weightGoals(): HasMany
    {
        return $this->hasMany(WeightGoal::class)->orderByDesc('id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MemberMeasurement::class)->orderBy('measured_on');
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class)->orderBy('taken_on');
    }

    public function aiReports(): HasMany
    {
        return $this->hasMany(AiReport::class)->latest('id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(MemberProgram::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    /** Most recent non-cancelled membership (by end date). */
    public function latestMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->ofMany(
            ['end_date' => 'max', 'id' => 'max'],
            fn ($q) => $q->where('status', '!=', MembershipStatus::Cancelled->value),
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    public function streak(): HasOne
    {
        return $this->hasOne(MemberStreak::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->orderByDesc('date');
    }

    // ---- Derived state ---------------------------------------------------

    public function currentMembership(): ?Membership
    {
        return $this->memberships()->valid()->first();
    }

    /**
     * Membership state used across the UI: active | upcoming | expired | none.
     */
    public function membershipState(): string
    {
        $latest = $this->relationLoaded('latestMembership') ? $this->latestMembership : $this->latestMembership()->first();

        if (! $latest) {
            return 'none';
        }

        $today = Carbon::today();

        return match (true) {
            $latest->end_date->lt($today) => 'expired',
            $latest->start_date->gt($today) => 'upcoming',
            default => 'active',
        };
    }

    public function pendingAmount(): float
    {
        return (float) $this->memberships()
            ->reorder()
            ->where('status', '!=', MembershipStatus::Cancelled->value)
            ->reorder()
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due')
            ->value('due');
    }

    public function photoUrl(): string
    {
        return $this->photo_path
            ? Storage::disk('public')->url($this->photo_path)
            : 'https://ui-avatars.com/api/?background=1f2a24&color=34d399&name='.urlencode($this->name);
    }

    // ---- Scopes ------------------------------------------------------------

    /** Restrict to what a given user may see: trainers only see their assigned members unless granted "members.all". */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('members.all')) {
            $query->where('trainer_id', $user->id);
        }

        return $query;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MemberStatus::Active->value);
    }

    /** Members holding a membership valid on the given date (default: today). */
    public function scopeWithValidMembership(Builder $query, $date = null): Builder
    {
        return $query->whereHas('memberships', fn ($q) => $q->valid($date));
    }

    /** Members who had memberships but none valid today and none upcoming. */
    public function scopeExpired(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->whereHas('memberships', fn ($q) => $q->where('status', '!=', MembershipStatus::Cancelled->value))
            ->whereDoesntHave('memberships', fn ($q) => $q->where('status', '!=', MembershipStatus::Cancelled->value)
                ->where('end_date', '>=', $today));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $query->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('member_code', 'like', "%{$term}%")));
    }
}
