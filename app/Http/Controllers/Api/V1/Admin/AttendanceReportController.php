<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\MemberStreak;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Attendance history (attendance.manage; trainers without "see all members" only see their own members)
 * and the gym-wide attendance reports and streak boards (attendance.reports), like the web pages.
 */
class AttendanceReportController extends AdminController
{
    public const MAX_HISTORY_DAYS = 366;

    /** Hours shown on the daily chart (5 am to 11 pm), as on the web report. */
    protected const FIRST_HOUR = 5;

    protected const LAST_HOUR = 23;

    public function __construct(protected StreakService $streaks) {}

    /** GET /admin/attendance?from=&to=&member_id=&search=&method=&page=&per_page= — newest first; from/to default to today. */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'member_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:80'],
            'method' => ['nullable', 'string', 'max:20'],
        ]);
        $user = $this->staff($request);
        $to = isset($data['to']) ? Carbon::parse($data['to']) : Carbon::today();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy();

        if ($from->diffInDays($to) + 1 > self::MAX_HISTORY_DAYS) {
            throw new ApiException('Choose at most '.self::MAX_HISTORY_DAYS.' days.', 'RANGE_TOO_LONG', 422, ['max_days' => self::MAX_HISTORY_DAYS]);
        }

        if (isset($data['member_id'])) {
            $this->visibleMemberOr404($request, (int) $data['member_id']);
        }

        $search = trim((string) ($data['search'] ?? ''));
        $query = Attendance::query()
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->when(! $user->can('members.all'), fn ($q) => $q->whereIn('member_id', Member::visibleTo($user)->select('id')))
            ->when($data['member_id'] ?? null, fn ($q, $id) => $q->where('member_id', (int) $id))
            ->when($data['method'] ?? null, fn ($q, $method) => $q->where('method', $method))
            ->when($search !== '', fn ($q) => $q->whereIn('member_id', Member::query()->search($search)->select('id')));

        $totals = (clone $query)->selectRaw('count(*) as visits, count(distinct member_id) as members, sum(case when check_out_at is null then 0 else 1 end) as checked_out')->first();
        $page = (clone $query)->with('member')->latest('check_in_at')->latest('id')->paginate($this->perPage($request));

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'check_ins' => (int) ($totals->visits ?? 0),
                'unique_members' => (int) ($totals->members ?? 0),
                'checked_out' => (int) ($totals->checked_out ?? 0),
            ],
            'attendance' => collect($page->items())->map(fn (Attendance $a) => $this->attendanceData($a))->values(),
            'pagination' => $this->pagination($page),
        ], 'Attendance.');
    }

    /**
     * GET /admin/attendance/reports?view=daily&date=YYYY-MM-DD&page=   check-ins per hour and the day's list;
     * GET /admin/attendance/reports?view=monthly&month=YYYY-MM&page=   check-ins per day and the most regular members.
     * Both cover the whole gym, like the web reports.
     */
    public function reports(Request $request): JsonResponse
    {
        $data = $request->validate(['view' => ['nullable', 'in:daily,monthly']]);

        return ($data['view'] ?? 'daily') === 'monthly' ? $this->monthly($request) : $this->daily($request);
    }

    protected function daily(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::today();

        $all = Attendance::whereDate('date', $date)->get(['check_in_at', 'check_out_at']);
        $hours = array_fill(self::FIRST_HOUR, self::LAST_HOUR - self::FIRST_HOUR + 1, 0);

        foreach ($all as $row) {
            $hour = (int) $row->check_in_at->format('G');
            if (isset($hours[$hour])) {
                $hours[$hour]++;
            }
        }

        $completed = $all->whereNotNull('check_out_at');
        $average = $completed->isNotEmpty() ? (int) round($completed->avg(fn ($a) => $a->check_in_at->diffInMinutes($a->check_out_at))) : null;
        $busiest = $all->isNotEmpty() ? array_search(max($hours), $hours, true) : null;
        $page = Attendance::with('member')->whereDate('date', $date)->orderBy('check_in_at')->orderBy('id')->paginate($this->perPage($request));

        return $this->success([
            'view' => 'daily',
            'date' => $date->toDateString(),
            'total' => $all->count(),
            'checked_out' => $completed->count(),
            'still_in' => $all->count() - $completed->count(),
            'average_minutes' => $average,
            'busiest_hour' => $busiest !== null && max($hours) > 0 ? ['hour' => $busiest, 'label' => Carbon::createFromTime($busiest)->format('ga'), 'check_ins' => $hours[$busiest]] : null,
            'by_hour' => collect($hours)->map(fn (int $count, int $hour) => [
                'hour' => $hour,
                'label' => Carbon::createFromTime($hour)->format('ga'),
                'check_ins' => $count,
            ])->values(),
            'list' => collect($page->items())->map(fn (Attendance $a) => $this->attendanceData($a))->values(),
            'pagination' => $this->pagination($page),
        ], 'Daily attendance.');
    }

    protected function monthly(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $start = isset($data['month']) ? Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfDay() : Carbon::today()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $perDay = Attendance::whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('DATE(date) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day) => [Carbon::parse($day)->toDateString() => (int) $total]);

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'label' => $day->format('d'), 'weekday' => $day->format('D'), 'check_ins' => (int) ($perDay[$day->toDateString()] ?? 0)];
        }

        $total = array_sum(array_column($days, 'check_ins'));
        $busiest = $perDay->isNotEmpty() ? $perDay->sortDesc()->keys()->first() : null;

        $top = Attendance::with('member')
            ->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('member_id, COUNT(*) as visits, MAX(date) as last_visit')
            ->groupBy('member_id')->orderByDesc('visits')->orderBy('member_id')
            ->paginate($this->perPage($request));

        return $this->success([
            'view' => 'monthly',
            'month' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'total' => $total,
            'unique_members' => $top->total(),
            'average_per_day' => round($total / max(1, $start->daysInMonth), 1),
            'busiest_day' => $busiest ? ['date' => $busiest, 'label' => Carbon::parse($busiest)->format('d M'), 'check_ins' => $perDay[$busiest]] : null,
            'by_day' => $days,
            'top_members' => collect($top->items())->map(fn (Attendance $row) => [
                'member' => $this->memberBrief($row->member),
                'visits' => (int) $row->visits,
                'last_visit' => $row->last_visit ? Carbon::parse($row->last_visit)->toDateString() : null,
            ])->values(),
            'pagination' => $this->pagination($top),
        ], 'Monthly attendance.');
    }

    /** GET /admin/streaks?board=current|best — leaderboard (top 25), members whose streak breaks today, and stats. */
    public function streaks(Request $request): JsonResponse
    {
        $data = $request->validate(['board' => ['nullable', 'in:current,best,longest']]);
        $board = ($data['board'] ?? 'current') === 'current' ? 'current' : 'best';
        $gym = current_gym();

        $leaderboard = MemberStreak::with('member')
            ->whereHas('member', fn ($q) => $q->active())
            ->when($board === 'current', fn ($q) => $q->where('current_streak', '>', 0)->orderByDesc('current_streak')->orderByDesc('last_visit_on'))
            ->when($board === 'best', fn ($q) => $q->where('longest_streak', '>', 0)->orderByDesc('longest_streak'))
            ->limit(25)
            ->get();

        $atRisk = MemberStreak::with('member')
            ->where('current_streak', '>', 0)
            ->whereHas('member', fn ($q) => $q->active())
            ->orderByDesc('current_streak')
            ->limit(200)
            ->get()
            ->filter(fn (MemberStreak $s) => $s->member && $this->streaks->calculate($s->member)['at_risk'])
            ->take(20);

        $rules = $gym->streakRules();

        return $this->success([
            'board' => $board,
            'stats' => [
                'active_streaks' => MemberStreak::where('current_streak', '>', 0)->count(),
                'average_streak' => round((float) MemberStreak::where('current_streak', '>', 0)->avg('current_streak'), 1),
                'best_streak' => (int) MemberStreak::max('longest_streak'),
                'active_members' => Member::active()->count(),
            ],
            'leaders' => $leaderboard->values()->map(fn (MemberStreak $s, int $i) => ['rank' => $i + 1] + $this->streakRow($s)),
            'at_risk' => $atRisk->values()->map(fn (MemberStreak $s) => $this->streakRow($s)),
            'rules' => [
                'grace_days' => $rules['grace_days'],
                'skip_closed_days' => $rules['skip_closed_days'],
                'milestones' => $rules['milestones'],
                'closed_weekdays' => $gym->closedWeekdays(),
            ],
            'can_edit_rules' => $this->staff($request)->can('gym.settings'),
        ], 'Streaks.');
    }

    /** POST /admin/streaks/recalculate — rebuild every member's streak from attendance. */
    public function recalculate(): JsonResponse
    {
        $count = $this->streaks->refreshGym(current_gym());

        return $this->success(['recalculated' => $count], "{$count} member streaks recalculated from attendance.");
    }

    /** @return array<string, mixed> */
    protected function streakRow(MemberStreak $streak): array
    {
        return [
            'member' => $this->memberBrief($streak->member),
            'current_streak' => (int) $streak->current_streak,
            'best_streak' => (int) $streak->longest_streak,
            'last_visit_on' => $streak->last_visit_on?->toDateString(),
        ];
    }
}
