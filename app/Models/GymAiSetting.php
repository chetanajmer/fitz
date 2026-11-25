<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;

/**
 * AI limits for one gym. The Super Admin sets allowed / monthly_credits / member_ceiling / photos_allowed;
 * the gym admin sets enabled / eligibility / eligible_plan_ids / member_limit / auto_monthly_report.
 */
class GymAiSetting extends Model
{
    use BelongsToGym;

    public const ELIGIBILITY = ['all' => 'Every member with a valid membership', 'plans' => 'Members on selected plans', 'selected' => 'Hand-picked members'];

    protected $fillable = [
        'allowed', 'monthly_credits', 'member_ceiling', 'photos_allowed',
        'enabled', 'eligibility', 'eligible_plan_ids', 'member_limit', 'auto_monthly_report',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
            'monthly_credits' => 'integer',
            'member_ceiling' => 'integer',
            'photos_allowed' => 'boolean',
            'enabled' => 'boolean',
            'eligible_plan_ids' => 'array',
            'member_limit' => 'integer',
            'auto_monthly_report' => 'boolean',
        ];
    }

    /** Credits one member may use per month: the gym's limit, never above the Super Admin's ceiling. */
    public function memberMonthlyLimit(): int
    {
        return min($this->member_limit, $this->member_ceiling);
    }
}
