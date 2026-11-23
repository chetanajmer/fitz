<?php

namespace App\Models;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    use BelongsToGym;

    // status / conversion fields are managed exclusively by EnquiryService.
    protected $fillable = [
        'name', 'phone', 'email', 'fitness_goal', 'interested_plan_id', 'source', 'follow_up_date', 'notes', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'source' => EnquirySource::class,
            'follow_up_date' => 'date',
            'converted_at' => 'datetime',
        ];
    }

    public function interestedPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'interested_plan_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_member_id')->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EnquiryActivity::class)->latest()->latest('id'); // newest first, stable within the same second
    }

    public function isConverted(): bool
    {
        return $this->status === EnquiryStatus::Converted || $this->converted_member_id !== null;
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [EnquiryStatus::Converted->value, EnquiryStatus::NotInterested->value]);
    }
}
