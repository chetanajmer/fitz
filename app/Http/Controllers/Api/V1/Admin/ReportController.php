<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Business reports for a date range (payments.manage): fees collected, fees billed and still pending,
 * best-selling plans, new members and renewals, who collected what, and a day-by-day or month-by-month series.
 * Gym-wide, like the web panel's Payments page. Everything is counted in the gym's timezone.
 */
class ReportController extends AdminController
{
    /** Longest range one report covers. */
    public const MAX_DAYS = 366;

    /** Up to this many days the series is per day; longer ranges are per month. */
    public const DAILY_SERIES_MAX_DAYS = 62;

    /** GET /admin/reports/overview?from=YYYY-MM-DD&to=YYYY-MM-DD (default: this month up to today) */
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'] ?? Carbon::today()->startOfMonth())->startOfDay();
        $to = Carbon::parse($data['to'] ?? Carbon::today())->endOfDay();
        $days = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;

        if ($days > self::MAX_DAYS) {
            throw new ApiException('Choose a range of at most '.self::MAX_DAYS.' days.', 'RANGE_TOO_LONG', 422, ['max_days' => self::MAX_DAYS]);
        }

        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        $collection = $this->collection($from, $to);
        $sales = $this->sales($from, $to);
        $previousCollected = (float) $this->paymentsIn($previousFrom, $previousTo)->sum('amount');
        $previousNewMembers = Member::whereBetween('joining_date', [$previousFrom->toDateString(), $previousTo->toDateString()])->count();
        $newMembers = Member::whereBetween('joining_date', [$from->toDateString(), $to->toDateString()])->count();

        return $this->success([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'collection' => $collection,
            'sales' => $sales,
            'dues' => $this->dues(),
            'members' => $this->members($from, $to, $newMembers),
            'plans' => $this->plans($from, $to),
            'collected_by' => $this->collectedBy($from, $to),
            'attendance' => [
                'check_ins' => Attendance::whereBetween('date', [$from->toDateString(), $to->toDateString()])->count(),
                'unique_members' => Attendance::whereBetween('date', [$from->toDateString(), $to->toDateString()])->distinct()->count('member_id'),
            ],
            'previous_period' => [
                'from' => $previousFrom->toDateString(),
                'to' => $previousTo->toDateString(),
                'collected' => round($previousCollected, 2),
                'collected_change_percent' => $this->change($collection['total'], $previousCollected),
                'new_members' => $previousNewMembers,
                'new_members_change_percent' => $this->change($newMembers, $previousNewMembers),
            ],
            'series' => $this->series($from, $to, $days),
        ], 'Report.');
    }

    protected function paymentsIn(Carbon $from, Carbon $to): Builder
    {
        // Qualified: the plans report joins memberships, which also has a status column.
        return Payment::query()->where('payments.status', 'completed')->whereBetween('payments.paid_at', [$from, $to]);
    }

    /** Memberships sold (created) in the range; cancelled ones are left out. */
    protected function soldIn(Carbon $from, Carbon $to): Builder
    {
        return Membership::query()->where('status', '!=', MembershipStatus::Cancelled->value)->whereBetween('created_at', [$from, $to]);
    }

    /** @return array<string, mixed> Fees received in the range, whichever membership they were for. */
    protected function collection(Carbon $from, Carbon $to): array
    {
        $byMethod = $this->paymentsIn($from, $to)->selectRaw('method, SUM(amount) as total, COUNT(*) as count')->groupBy('method')->get()
            ->mapWithKeys(fn ($row) => [$row->method->value => ['amount' => round((float) $row->total, 2), 'count' => (int) $row->count]]);
        $total = round((float) $byMethod->sum('amount'), 2);
        $count = (int) $byMethod->sum('count');

        return [
            'total' => $total,
            'payments' => $count,
            'paying_members' => $this->paymentsIn($from, $to)->distinct()->count('member_id'),
            'average_payment' => $count ? round($total / $count, 2) : 0.0,
            'by_method' => collect(PaymentMethod::values())->mapWithKeys(fn (string $m) => [$m => $byMethod[$m] ?? ['amount' => 0.0, 'count' => 0]])->all(),
        ];
    }

    /** @return array<string, mixed> Memberships sold in the range: what was billed, paid so far and still pending on them. */
    protected function sales(Carbon $from, Carbon $to): array
    {
        $row = $this->soldIn($from, $to)->selectRaw(
            'COUNT(*) as sold, SUM(CASE WHEN renewed_from_id IS NULL THEN 1 ELSE 0 END) as new_sales, '
            .'COALESCE(SUM(price), 0) as gross, COALESCE(SUM(discount), 0) as discount, COALESCE(SUM(total_amount), 0) as billed, '
            .'COALESCE(SUM(amount_paid), 0) as paid'
        )->first();

        $sold = (int) $row->sold;
        $newSales = (int) $row->new_sales;

        return [
            'memberships_sold' => $sold,
            'new' => $newSales,
            'renewals' => $sold - $newSales,
            'gross' => round((float) $row->gross, 2),
            'discount' => round((float) $row->discount, 2),
            'billed' => round((float) $row->billed, 2),
            'paid' => round((float) $row->paid, 2),
            'pending' => round(max(0, (float) $row->billed - (float) $row->paid), 2),
            'cancelled' => Membership::where('status', MembershipStatus::Cancelled->value)->whereBetween('cancelled_at', [$from, $to])->count(),
        ];
    }

    /** @return array<string, mixed> Everything still owed today, across all time. */
    protected function dues(): array
    {
        $row = Membership::withBalance()->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due, COUNT(*) as memberships, COUNT(DISTINCT member_id) as members')->first();
        $today = Carbon::today()->toDateString();
        $overdue = Membership::withBalance()->whereDate('end_date', '<', $today)->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due, COUNT(*) as memberships')->first();

        return [
            'total_due' => round((float) $row->due, 2),
            'memberships' => (int) $row->memberships,
            'members' => (int) $row->members,
            // Dues on memberships that have already ended.
            'overdue' => round((float) $overdue->due, 2),
            'overdue_memberships' => (int) $overdue->memberships,
        ];
    }

    /** @return array<string, int> */
    protected function members(Carbon $from, Carbon $to, int $newMembers): array
    {
        $today = Carbon::today();

        return [
            'new' => $newMembers,
            // Members whose membership ended in the range and who have not renewed since.
            'lapsed' => Membership::where('status', '!=', MembershipStatus::Cancelled->value)
                ->whereBetween('end_date', [$from->toDateString(), min($to, $today->copy()->subDay()->endOfDay())->toDateString()])
                ->whereNotExists(fn ($q) => $q->from('memberships as newer')->whereColumn('newer.member_id', 'memberships.member_id')
                    ->where('newer.status', '!=', MembershipStatus::Cancelled->value)->whereColumn('newer.end_date', '>', 'memberships.end_date'))
                ->distinct()->count('member_id'),
            // Snapshot today (not limited to the range).
            'total' => Member::count(),
            'active' => Member::active()->withValidMembership()->count(),
            'expired' => Member::active()->expired()->count(),
            'expiring_in_15_days' => Membership::valid()->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(15)->toDateString()])
                ->whereNotExists(fn ($q) => $q->from('memberships as renewal')->whereColumn('renewal.member_id', 'memberships.member_id')
                    ->where('renewal.status', '!=', MembershipStatus::Cancelled->value)->whereColumn('renewal.start_date', '>', 'memberships.end_date'))
                ->distinct()->count('member_id'),
        ];
    }

    /** @return array<string, mixed> Plans by memberships sold in the range (best seller first), with what they brought in. */
    protected function plans(Carbon $from, Carbon $to): array
    {
        $sold = $this->soldIn($from, $to)
            ->selectRaw('membership_plan_id, MAX(plan_name) as plan_name, COUNT(*) as sold, SUM(CASE WHEN renewed_from_id IS NULL THEN 0 ELSE 1 END) as renewals, SUM(total_amount) as billed, SUM(amount_paid) as paid')
            ->groupBy('membership_plan_id')
            ->get();

        $collected = $this->paymentsIn($from, $to)
            ->join('memberships', 'memberships.id', '=', 'payments.membership_id')
            ->selectRaw('memberships.membership_plan_id as plan_id, MAX(memberships.plan_name) as plan_name, SUM(payments.amount) as collected')
            ->groupBy('memberships.membership_plan_id')
            ->get()
            ->keyBy('plan_id');

        $totalSold = max(1, (int) $sold->sum('sold'));
        $planIds = $sold->pluck('membership_plan_id')->merge($collected->keys())->filter()->unique();

        $rows = $planIds->map(function ($planId) use ($sold, $collected, $totalSold) {
            $s = $sold->firstWhere('membership_plan_id', $planId);
            $c = $collected->get($planId);

            return [
                'plan_id' => (int) $planId,
                'plan_name' => $s->plan_name ?? $c->plan_name,
                'sold' => (int) ($s->sold ?? 0),
                'renewals' => (int) ($s->renewals ?? 0),
                'share_percent' => round((int) ($s->sold ?? 0) / $totalSold * 100, 1),
                'billed' => round((float) ($s->billed ?? 0), 2),
                'pending' => round(max(0, (float) ($s->billed ?? 0) - (float) ($s->paid ?? 0)), 2),
                'collected' => round((float) ($c->collected ?? 0), 2),
            ];
        })->sortBy([['sold', 'desc'], ['billed', 'desc']])->values();

        return [
            'most_selling' => $rows->firstWhere('sold', '>', 0),
            'top_earning' => $rows->sortByDesc('collected')->firstWhere('collected', '>', 0),
            'rows' => $rows->all(),
        ];
    }

    /** @return list<array<string, mixed>> Fees received per staff member in the range. */
    protected function collectedBy(Carbon $from, Carbon $to): array
    {
        $rows = $this->paymentsIn($from, $to)->selectRaw('received_by, SUM(amount) as total, COUNT(*) as count')->groupBy('received_by')->orderByDesc('total')->get();
        $users = User::whereIn('id', $rows->pluck('received_by')->filter())->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'user' => $row->received_by && $users->has($row->received_by) ? $this->person($users->get($row->received_by)) : null,
            'amount' => round((float) $row->total, 2),
            'count' => (int) $row->count,
        ])->values()->all();
    }

    /** @return array<string, mixed> Per day (short ranges) or per month: collected, memberships sold and new members. */
    protected function series(Carbon $from, Carbon $to, int $days): array
    {
        $daily = $days <= self::DAILY_SERIES_MAX_DAYS;
        $key = fn (string $date) => $daily ? Carbon::parse($date)->toDateString() : Carbon::parse($date)->format('Y-m');

        $collected = $this->paymentsIn($from, $to)->selectRaw('DATE(paid_at) as day, SUM(amount) as total')->groupBy(DB::raw('DATE(paid_at)'))->get()
            ->groupBy(fn ($r) => $key($r->day))->map(fn ($g) => $g->sum('total'));
        $sold = $this->soldIn($from, $to)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy(DB::raw('DATE(created_at)'))->get()
            ->groupBy(fn ($r) => $key($r->day))->map(fn ($g) => $g->sum('total'));
        $joined = Member::whereBetween('joining_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('joining_date as day, COUNT(*) as total')->groupBy('joining_date')->get()
            ->groupBy(fn ($r) => $key((string) $r->getRawOriginal('day')))->map(fn ($g) => $g->sum('total'));

        $points = [];
        $cursor = $daily ? $from->copy()->startOfDay() : $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $k = $daily ? $cursor->toDateString() : $cursor->format('Y-m');
            $points[] = [
                'key' => $k,
                'label' => $daily ? $cursor->format('d M') : $cursor->format('M Y'),
                'collected' => round((float) ($collected[$k] ?? 0), 2),
                'memberships_sold' => (int) ($sold[$k] ?? 0),
                'new_members' => (int) ($joined[$k] ?? 0),
            ];
            $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow();
        }

        return ['interval' => $daily ? 'day' : 'month', 'points' => $points];
    }

    protected function change(float|int $current, float|int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }
}
