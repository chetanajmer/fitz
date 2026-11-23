<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MemberStatus;
use App\Models\Attendance;
use App\Models\Enquiry;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * All queries here run inside the tenant scope (BelongsToGym), so they only ever
 * see the current gym's rows. Platform stats explicitly bypass via withoutGlobalScopes.
 */
class DashboardService
{
    public function gymStats(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        return [
            'total_members' => Member::count(),
            'active_members' => Member::active()->withValidMembership()->count(),
            'inactive_members' => Member::where('status', MemberStatus::Inactive->value)->count(),
            'expired_members' => Member::active()->expired()->count(),
            'new_enquiries' => Enquiry::where('status', EnquiryStatus::New->value)->count(),
            'followups_today' => Enquiry::open()->whereDate('follow_up_date', '<=', $today)->count(),
            'monthly_revenue' => (float) Payment::where('status', 'completed')->where('paid_at', '>=', $monthStart)->sum('amount'),
            'pending_fees' => (float) Membership::withBalance()->sum(DB::raw('total_amount - amount_paid')),
            'today_attendance' => Attendance::whereDate('date', $today)->count(),
            'currently_inside' => Attendance::whereDate('date', $today)->whereNull('check_out_at')->count(),
        ];
    }

    public function upcomingExpiries(int $days = 7, int $limit = 8)
    {
        $today = Carbon::today();

        return Membership::with('member')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($days)->toDateString()])
            // Skip members who already renewed.
            ->whereNotExists(fn ($q) => $q->from('memberships as renewal')
                ->whereColumn('renewal.member_id', 'memberships.member_id')
                ->where('renewal.status', '!=', 'cancelled')
                ->whereColumn('renewal.start_date', '>', 'memberships.end_date'))
            ->orderBy('end_date')
            ->limit($limit)
            ->get();
    }

    public function recentPayments(int $limit = 6)
    {
        return Payment::with('member')->latest('paid_at')->limit($limit)->get();
    }

    public function recentEnquiries(int $limit = 6)
    {
        return Enquiry::latest()->limit($limit)->get();
    }

    /** Last N months: new members per month and cumulative total. */
    public function memberGrowth(int $months = 6): array
    {
        $labels = $new = $total = [];
        $start = Carbon::today()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $from = $start->copy()->addMonths($i);
            $to = $from->copy()->endOfMonth();
            $labels[] = $from->format('M y');
            $new[] = Member::whereBetween('joining_date', [$from->toDateString(), $to->toDateString()])->count();
            $total[] = Member::whereDate('joining_date', '<=', $to->toDateString())->count();
        }

        return compact('labels', 'new', 'total');
    }

    public function revenueSeries(int $months = 6): array
    {
        $labels = $values = [];
        $start = Carbon::today()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $from = $start->copy()->addMonths($i);
            $labels[] = $from->format('M y');
            $values[] = (float) Payment::where('status', 'completed')
                ->whereBetween('paid_at', [$from, $from->copy()->endOfMonth()])->sum('amount');
        }

        return compact('labels', 'values');
    }

    public function trainerStats(User $trainer): array
    {
        $members = Member::where('trainer_id', $trainer->id);
        $ids = (clone $members)->pluck('id');

        return [
            'assigned' => (clone $members)->count(),
            'active' => (clone $members)->active()->withValidMembership()->count(),
            'expiring' => Membership::whereIn('member_id', $ids)->where('status', '!=', 'cancelled')
                ->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays(7)])->count(),
            'today_attendance' => Attendance::whereIn('member_id', $ids)->whereDate('date', Carbon::today())->count(),
        ];
    }

    // ---- Platform -----------------------------------------------------------

    public function platformStats(): array
    {
        $today = Carbon::today()->toDateString();

        return [
            'total_gyms' => Gym::count(),
            'active_gyms' => Gym::operational()->count(),
            'suspended_gyms' => Gym::where('status', 'suspended')->count(),
            'total_members' => Member::withoutGlobalScopes()->whereNull('deleted_at')->count(),
            'active_members' => Member::withoutGlobalScopes()->whereNull('deleted_at')->where('status', 'active')
                ->whereHas('memberships', fn ($q) => $q->withoutGlobalScopes()->valid($today))->count(),
            'billed_total' => (float) SaasInvoice::where('status', '!=', InvoiceStatus::Void->value)->sum('total'),
            'collected_total' => (float) SaasPayment::sum('amount'),
            'outstanding' => (float) SaasInvoice::outstanding()->sum(DB::raw('total - amount_paid')),
            'collected_this_month' => (float) SaasPayment::where('paid_at', '>=', Carbon::today()->startOfMonth())->sum('amount'),
        ];
    }

    public function platformRevenueSeries(int $months = 6): array
    {
        $labels = $billed = $collected = [];
        $start = Carbon::today()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $from = $start->copy()->addMonths($i);
            $labels[] = $from->format('M y');
            $billed[] = (float) SaasInvoice::whereDate('period_start', $from)->where('status', '!=', 'void')->sum('total');
            $collected[] = (float) SaasPayment::whereBetween('paid_at', [$from, $from->copy()->endOfMonth()])->sum('amount');
        }

        return compact('labels', 'billed', 'collected');
    }

    public function gymSignupSeries(int $months = 6): array
    {
        $labels = $values = [];
        $start = Carbon::today()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $from = $start->copy()->addMonths($i);
            $labels[] = $from->format('M y');
            $values[] = Gym::whereBetween('created_at', [$from, $from->copy()->endOfMonth()])->count();
        }

        return compact('labels', 'values');
    }
}
