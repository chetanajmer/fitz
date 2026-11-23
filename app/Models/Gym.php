<?php

namespace App\Models;

use App\Enums\GymStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gym extends Model
{
    public const DEFAULT_SETTINGS = [
        'primary_color' => '#4f46e5',
        'secondary_color' => '#0ea5e9',
        'tagline' => null,
        'opening_hours' => [
            'monday' => ['open' => '06:00', 'close' => '22:00', 'closed' => false],
            'tuesday' => ['open' => '06:00', 'close' => '22:00', 'closed' => false],
            'wednesday' => ['open' => '06:00', 'close' => '22:00', 'closed' => false],
            'thursday' => ['open' => '06:00', 'close' => '22:00', 'closed' => false],
            'friday' => ['open' => '06:00', 'close' => '22:00', 'closed' => false],
            'saturday' => ['open' => '07:00', 'close' => '20:00', 'closed' => false],
            'sunday' => ['open' => '08:00', 'close' => '12:00', 'closed' => true],
        ],
        'facilities' => [],
        'social' => [
            'website' => null, 'instagram' => null, 'facebook' => null, 'youtube' => null, 'whatsapp' => null,
        ],
        'attendance' => [
            'member_self_checkin' => true, // members may check in from the portal / mobile app
        ],
        // Attendance streak rules (see StreakService).
        'streak' => [
            'grace_days' => 1,           // rest days allowed between two visits without breaking the streak
            'skip_closed_days' => true,   // days the gym is closed (opening hours) never break a streak
            'milestones' => [7, 14, 30, 60, 100],
        ],
    ];

    // owner_id, slug, join_token, register_token, city_id and status are set by services only.
    protected $fillable = ['name', 'phone', 'email', 'address', 'city', 'logo_path', 'description'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'status' => GymStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** The managed city (the `city` text column mirrors its name for display). */
    public function cityRecord(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->withoutGlobalScopes([Scopes\GymScope::class]);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class)->withoutGlobalScopes([Scopes\GymScope::class]);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GymPhoto::class)->withoutGlobalScopes([Scopes\GymScope::class])->orderBy('sort_order');
    }

    public function saasInvoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    // ---- Settings -------------------------------------------------------

    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->allSettings(), $key, $default);
    }

    public function allSettings(): array
    {
        return array_replace_recursive(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    /**
     * Streak rules with defaults. Read directly (not via allSettings) so a custom milestone
     * list replaces the default list instead of being merged into it index by index.
     *
     * @return array{grace_days:int, skip_closed_days:bool, milestones:list<int>}
     */
    public function streakRules(): array
    {
        $defaults = self::DEFAULT_SETTINGS['streak'];
        $custom = $this->settings['streak'] ?? [];
        $milestones = collect($custom['milestones'] ?? $defaults['milestones'])->map(fn ($m) => (int) $m)->filter(fn ($m) => $m > 0)->unique()->sort()->values()->all();

        return [
            'grace_days' => max(0, min(7, (int) ($custom['grace_days'] ?? $defaults['grace_days']))),
            'skip_closed_days' => (bool) ($custom['skip_closed_days'] ?? $defaults['skip_closed_days']),
            'milestones' => $milestones,
        ];
    }

    public function allowsSelfCheckIn(): bool
    {
        return (bool) ($this->settings['attendance']['member_self_checkin'] ?? self::DEFAULT_SETTINGS['attendance']['member_self_checkin']);
    }

    /** Lower-case weekday names the gym is closed on, from opening hours. */
    public function closedWeekdays(): array
    {
        return collect($this->setting('opening_hours', []))->filter(fn ($h) => ! empty($h['closed']))->keys()->all();
    }

    public function primaryColor(): string
    {
        return $this->safeColor($this->setting('primary_color'), '#4f46e5');
    }

    public function secondaryColor(): string
    {
        return $this->safeColor($this->setting('secondary_color'), '#0ea5e9');
    }

    protected function safeColor(?string $value, string $fallback): string
    {
        // Only allow hex colours: values are injected into inline CSS on the public page.
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
    }

    // ---- URLs -----------------------------------------------------------

    public function publicUrl(): string
    {
        return route('gym.public', $this->slug);
    }

    public function joinUrl(): string
    {
        return route('gym.join', $this->join_token);
    }

    /** Self-registration form that creates a member (encoded in the attendance-desk QR). */
    public function registerUrl(): string
    {
        return route('gym.register', $this->register_token);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');
    }

    // ---- Status ---------------------------------------------------------

    public function isOperational(): bool
    {
        return $this->status === GymStatus::Active;
    }

    public function scopeOperational($query)
    {
        return $query->where('status', GymStatus::Active->value);
    }

    // ---- Identifiers ----------------------------------------------------

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'gym';
        $base = Str::limit($base, 50, '');
        $reserved = config('gymai.reserved_slugs', []);
        $slug = $base;
        $i = 2;

        while (in_array($slug, $reserved, true)
            || static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function generateJoinToken(): string
    {
        do {
            // 40 chars of URL-safe randomness (~238 bits) — unguessable.
            $token = Str::random(40);
        } while (static::where('join_token', $token)->exists());

        return $token;
    }

    /** Separate secret for the desk self-registration QR, so the public join link cannot create members. */
    public static function generateRegisterToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('register_token', $token)->exists());

        return $token;
    }
}
