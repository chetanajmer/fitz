<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    // gym_id is intentionally NOT fillable: it is always assigned by server-side services.
    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status', 'profile_photo_path', 'bio', 'specialization',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function ownedGym(): HasOne
    {
        return $this->hasOne(Gym::class, 'owner_id');
    }

    /** Member profile for users with the "member" role. */
    public function memberProfile(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /** Members assigned to this trainer. */
    public function assignedMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'trainer_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin->value);
    }

    public function isGymAdmin(): bool
    {
        return $this->hasRole(Role::GymAdmin->value);
    }

    public function isTrainer(): bool
    {
        return $this->hasRole(Role::Trainer->value);
    }

    public function isMember(): bool
    {
        return $this->hasRole(Role::Member->value);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Where this user lands after login. */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isSuperAdmin() => route('platform.dashboard'),
            $this->isGymAdmin(), $this->isTrainer() => route('gym.dashboard'),
            $this->isMember() => route('portal.dashboard'),
            default => route('home'),
        };
    }

    public function photoUrl(): string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : 'https://ui-avatars.com/api/?background=222222&color=ff6b00&name='.urlencode($this->name);
    }

    public function scopeTrainersOf($query, int $gymId)
    {
        return $query->where('gym_id', $gymId)->role(Role::Trainer->value);
    }
}
