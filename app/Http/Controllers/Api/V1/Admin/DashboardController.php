<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Attendance;
use App\Models\Enquiry;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** GET /api/v1/admin/dashboard — the same figures as the web panel dashboard (gym admin or trainer). */
class DashboardController extends AdminController
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user = $this->staff($request);

        if (! $user->isGymAdmin()) {
            return $this->trainer($request, $dashboard);
        }

        $growth = $dashboard->memberGrowth();
        $revenue = $dashboard->revenueSeries();

        return $this->success([
            'role' => 'gym_admin',
            'stats' => $dashboard->gymStats(),
            'expiring' => $dashboard->upcomingExpiries()->map(fn (Membership $m) => $this->expiringData($m))->all(),
            'recent_payments' => $dashboard->recentPayments()->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_no' => $p->receipt_number,
                'member' => $this->memberBrief($p->member),
                'amount' => (float) $p->amount,
                'method' => $p->method?->value ?? $p->method,
                'paid_at' => $this->iso($p->paid_at),
            ])->all(),
            'recent_enquiries' => $dashboard->recentEnquiries()->map(fn (Enquiry $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'phone' => $e->phone,
                'source' => $e->source?->value ?? $e->source,
                'status' => $e->status?->value ?? $e->status,
                'follow_up_date' => $e->follow_up_date?->toDateString(),
                'created_at' => $this->iso($e->created_at),
            ])->all(),
            'member_growth' => collect($growth['labels'])->map(fn ($month, $i) => ['month' => $month, 'count' => $growth['new'][$i], 'total' => $growth['total'][$i]])->all(),
            'revenue' => collect($revenue['labels'])->map(fn ($month, $i) => ['month' => $month, 'amount' => $revenue['values'][$i]])->all(),
        ], 'Dashboard.');
    }

    protected function trainer(Request $request, DashboardService $dashboard): JsonResponse
    {
        $trainer = $this->staff($request);
        $memberIds = Member::where('trainer_id', $trainer->id)->pluck('id');
        $stats = $dashboard->trainerStats($trainer);
        $today = Carbon::today();

        $todayAttendance = Attendance::with('member')->whereIn('member_id', $memberIds)->whereDate('date', $today)->latest('check_in_at')->get();
        $expiring = Membership::with('member')->whereIn('member_id', $memberIds)->where('status', '!=', 'cancelled')
            ->whereBetween('end_date', [$today, $today->copy()->addDays(7)])->orderBy('end_date')->get();

        return $this->success([
            'role' => 'trainer',
            'stats' => [
                'my_members' => $stats['assigned'],
                'active_members' => $stats['active'],
                'checked_in_today' => $stats['today_attendance'],
                'inside_now' => $todayAttendance->whereNull('check_out_at')->count(),
                'expiring_soon' => $stats['expiring'],
            ],
            'members' => Member::where('trainer_id', $trainer->id)->with('latestMembership')->orderBy('name')->limit(12)->get()
                ->map(fn (Member $m) => $this->memberBrief($m) + [
                    'status' => $m->status->value,
                    'membership' => $m->latestMembership ? $this->membershipData($m->latestMembership) : null,
                ])->all(),
            'today_attendance' => $todayAttendance->map(fn (Attendance $a) => $this->attendanceData($a))->all(),
            'expiring' => $expiring->map(fn (Membership $m) => $this->expiringData($m))->all(),
        ], 'Dashboard.');
    }

    /** @return array<string, mixed> */
    protected function expiringData(Membership $membership): array
    {
        return $this->membershipData($membership) + ['member' => $this->memberBrief($membership->member)];
    }
}
