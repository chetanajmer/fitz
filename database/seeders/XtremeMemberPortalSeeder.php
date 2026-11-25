<?php

namespace Database\Seeders;

use App\Enums\FitnessPlanType;
use App\Models\Attendance;
use App\Models\FitnessProgram;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\MembershipService;
use App\Services\ProgramService;
use App\Services\WeightTrackingService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Fills the member dashboard (/me) of the Xtreme test members (member@xtreme.test, member2@xtreme.test):
 * workout + diet programs with logged days, a visit streak, an earlier membership with its payment,
 * and weight logs with a goal. Run after XtremeDemoSeeder:
 * php artisan db:seed --class=XtremeMemberPortalSeeder
 */
class XtremeMemberPortalSeeder extends Seeder
{
    /** email => [workout program, diet program, start kg, goal kg] */
    protected const MEMBERS = [
        'member@xtreme.test' => ['prog-wo-mg-beginner', 'prog-diet-mg-egg', 72.0, 76.0],
        'member2@xtreme.test' => ['prog-wo-wl-beginner', 'prog-diet-wl-veg', 68.0, 62.0],
    ];

    public function run(): void
    {
        $gym = Gym::where('slug', 'xtreme-fitness-gym')->first();

        if (! $gym) {
            $this->command?->error('Xtreme Fitness Gym not found.');

            return;
        }

        app(TenantManager::class)->runAs($gym, function () use ($gym) {
            $programs = app(ProgramService::class);
            $programs->importDefaults(FitnessPlanType::Workout, collect(self::MEMBERS)->pluck(0)->all());
            $programs->importDefaults(FitnessPlanType::Diet, collect(self::MEMBERS)->pluck(1)->all());
            $owner = $gym->owner;

            foreach (self::MEMBERS as $email => [$workoutKey, $dietKey, $startKg, $goalKg]) {
                $member = Member::where('email', $email)->first();

                if (! $member) {
                    $this->command?->warn("{$email} not found — run XtremeDemoSeeder first.");

                    continue;
                }

                if ($member->programs()->exists()) {
                    $this->command?->warn("{$email} already has programs — skipping.");

                    continue;
                }

                $this->assignPrograms($member, $workoutKey, $dietKey, $owner);
                $this->addStreak($member, $gym->owner_id);
                $this->addPastMembership($member);
                $this->addWeightLogs($member, $startKg, $goalKg);
            }
        });

        $this->command?->info('Member dashboard data added for the Xtreme test members.');
    }

    /** One workout and one diet program, started 6 days ago, with most past days marked done. */
    protected function assignPrograms(Member $member, string $workoutKey, string $dietKey, $by): void
    {
        $service = app(ProgramService::class);
        $start = Carbon::today()->subDays(6);

        foreach ([FitnessPlanType::Workout->value => $workoutKey, FitnessPlanType::Diet->value => $dietKey] as $type => $key) {
            $program = FitnessProgram::where('library_key', $key)->first();
            if (! $program) {
                continue;
            }
            $service->assign($member, $program, $start->toDateString(), $by, 'Started with the trainer at the front desk.');

            foreach (range(6, 1) as $daysAgo) {
                try {
                    $service->logDay($member, FitnessPlanType::from($type), Carbon::today()->subDays($daysAgo), $daysAgo === 3 ? 'skipped' : 'done');
                } catch (ValidationException) {
                    // Rest day or no plan that day: nothing to log.
                }
            }
        }
    }

    /** Visits on each open day of the last two weeks, so the streak and the week dots fill up. */
    protected function addStreak(Member $member, int $recordedBy): void
    {
        $member->loadMissing('gym');

        foreach (range(13, 1) as $daysAgo) {
            $day = Carbon::today()->subDays($daysAgo);
            if (! $member->gym->isOpenOn($day) || Attendance::where('member_id', $member->id)->whereDate('date', $day)->exists()) {
                continue;
            }
            $in = $day->copy()->setTime(rand(6, 8), rand(0, 59));
            $visit = new Attendance;
            $visit->member_id = $member->id;
            $visit->date = $day->toDateString();
            $visit->check_in_at = $in;
            $visit->check_out_at = $in->copy()->addMinutes(rand(55, 95));
            $visit->method = 'qr';
            $visit->recorded_by = $recordedBy;
            $visit->save();
        }
    }

    /** A paid quarterly membership that ended the day before the current one started. */
    protected function addPastMembership(Member $member): void
    {
        $first = $member->memberships()->reorder()->orderBy('start_date')->first();
        $plan = MembershipPlan::where('duration_type', 'quarterly')->first();

        if (! $first || ! $plan) {
            return;
        }

        $start = $first->start_date->copy()->subMonthsNoOverflow(3);
        try {
            app(MembershipService::class)->assign($member, $plan, $start->toDateString(), 0, 'Previous membership', [
                'amount' => (float) $plan->price, 'method' => 'upi', 'paid_at' => $start->copy()->setTime(10, 30)->toDateTimeString(),
            ]);
            app(MembershipService::class)->refreshStatuses();
        } catch (ValidationException) {
            // Dates overlap an existing membership: keep the history as it is.
        }
    }

    /** A weigh-in every two weeks over the last three months, trending towards the goal. */
    protected function addWeightLogs(Member $member, float $startKg, float $goalKg): void
    {
        $weights = app(WeightTrackingService::class);
        $step = ($goalKg - $startKg) / 10;

        foreach (range(6, 0) as $i => $fortnightsAgo) {
            $weights->record($member, round($startKg + $step * $i + (rand(-3, 3) / 10), 1), Carbon::today()->subWeeks($fortnightsAgo * 2)->toDateString(), 'app');
        }
        $weights->setGoal($member, $goalKg, Carbon::today()->addMonths(3)->toDateString(), null, 'app');
    }
}
