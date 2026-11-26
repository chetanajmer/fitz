<?php

namespace App\Models;

use App\Enums\GymStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gym extends Model
{
    public const DEFAULT_SETTINGS = [
        'primary_color' => '#ff6b00',
        'secondary_color' => '#111111',
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
        // Weight tracking: how often members are asked for a weigh-in (see WeightTrackingService).
        'progress' => [
            'weigh_in_every_days' => 15,
        ],
    ];

    /** Dashboard theme colours (Fitnix black + electric orange): primary accent and navigation background. */
    public const DASHBOARD_THEME_DEFAULTS = ['primary' => '#ff6b00', 'secondary' => '#0d0d0d'];

    /** Dashboards whose colours a gym can change. */
    public const DASHBOARD_AREAS = ['admin' => 'Admin dashboard', 'member' => 'Member dashboard'];

    // owner_id, slug, join_token, register_token, checkin_token, city_id and status are set by services only.
    protected $fillable = ['name', 'phone', 'email', 'address', 'map_url', 'city', 'logo_path', 'description'];

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

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
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

    /**
     * How complete the public profile is (shown to members choosing a gym), and what is missing.
     *
     * @return array{percent: int, missing: list<string>}
     */
    public function profileCompleteness(): array
    {
        $checks = [
            'Logo' => (bool) $this->logo_path,
            'Banner image' => (bool) $this->banner_path,
            'Description' => filled($this->description),
            'Phone' => filled($this->phone),
            'Address' => filled($this->address),
            'Google Maps link' => filled($this->map_url),
            'Locality' => (bool) $this->locality_id,
            'Opening hours' => ! empty($this->settings['opening_hours'] ?? null),
            'Gym photos' => $this->photos()->exists(),
            'A public membership plan' => $this->plans()->where('is_active', true)->where('is_public', true)->exists(),
        ];

        return [
            'percent' => (int) round(count(array_filter($checks)) / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn (bool $done) => ! $done)),
        ];
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

    /** Days between weigh-in reminders for members (7, 15 or 30). */
    /** Whether the gym is open on that day of the week (opening hours). */
    public function isOpenOn(Carbon $date): bool
    {
        $day = $this->allSettings()['opening_hours'][strtolower($date->format('l'))] ?? null;

        return ! ($day['closed'] ?? false);
    }

    public function weighInEveryDays(): int
    {
        $days = (int) ($this->settings['progress']['weigh_in_every_days'] ?? self::DEFAULT_SETTINGS['progress']['weigh_in_every_days']);

        return in_array($days, [7, 15, 30], true) ? $days : 15;
    }

    /**
     * Joining fee and GST for member fees (Gym Profile, Fees & GST). Prices include GST.
     *
     * @return array{joining_fee: float, gst_enabled: bool, gstin: ?string, gst_rate: float}
     */
    public function feeSettings(): array
    {
        $fees = $this->settings['fees'] ?? [];

        return [
            'joining_fee' => round(max(0, (float) ($fees['joining_fee'] ?? 0)), 2),
            'gst_enabled' => (bool) ($fees['gst_enabled'] ?? false) && filled($fees['gstin'] ?? null),
            'gstin' => $fees['gstin'] ?? null,
            'gst_rate' => (float) ($fees['gst_rate'] ?? 18),
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
        return $this->safeColor($this->setting('primary_color'), '#ff6b00');
    }

    public function secondaryColor(): string
    {
        return $this->safeColor($this->setting('secondary_color'), '#111111');
    }

    /**
     * The colours a gym picked for one of its dashboards ('admin' panel or 'member' app), falling back to
     * the Fitnix theme. `custom` is false while the gym uses the default theme.
     *
     * @return array{primary: string, secondary: string, custom: bool}
     */
    public function dashboardColors(string $area): array
    {
        $chosen = $this->settings['dashboard_theme'][$area] ?? [];
        $primary = $this->safeColor($chosen['primary'] ?? null, self::DASHBOARD_THEME_DEFAULTS['primary']);
        $secondary = $this->safeColor($chosen['secondary'] ?? null, self::DASHBOARD_THEME_DEFAULTS['secondary']);

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'custom' => strcasecmp($primary, self::DASHBOARD_THEME_DEFAULTS['primary']) !== 0
                || strcasecmp($secondary, self::DASHBOARD_THEME_DEFAULTS['secondary']) !== 0,
        ];
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

    /** The gym's own Google Maps link, or a Google Maps search for its name and address. */
    public function directionsUrl(): string
    {
        return $this->map_url ?: 'https://www.google.com/maps/search/?api=1&query='
            .rawurlencode(collect([$this->name, $this->address, $this->locality?->name, $this->city])->filter()->unique()->implode(', '));
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

    /** Secret for the check-in QR that members scan at the gym. */
    public static function generateCheckinToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('checkin_token', $token)->exists());

        return $token;
    }
}
