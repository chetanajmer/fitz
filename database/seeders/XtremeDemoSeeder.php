<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\MemberService;
use App\Services\MembershipService;
use App\Services\TrainerService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dummy plans, trainers, members, memberships, payments and attendance for "Xtreme Fitness Gym".
 * php artisan db:seed --class=XtremeDemoSeeder
 * Trainer logins: *.trainer@xtreme.test / password · Member portal: member@xtreme.test and member2@xtreme.test / password
 */
class XtremeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::where('slug', 'xtreme-fitness-gym')->first();

        if (! $gym) {
            $this->command?->error('Xtreme Fitness Gym not found — register it first.');

            return;
        }

        if (Member::withoutGlobalScopes()->where('gym_id', $gym->id)->exists()) {
            $this->command?->warn('Xtreme Fitness Gym already has members — skipping.');

            return;
        }

        $ownerId = $gym->owner_id;

        app(TenantManager::class)->runAs($gym, function () use ($ownerId) {
            $plans = MembershipPlan::query()->exists()
                ? MembershipPlan::orderBy('sort_order')->get()
                : collect([
                    ['Monthly', 'monthly', 1500], ['Quarterly', 'quarterly', 4000], ['Half-yearly', 'half_yearly', 7500],
                    ['Yearly', 'yearly', 13000], ['7-day Trial', 'custom', 300],
                ])->map(fn ($p, $i) => MembershipPlan::create([
                    'name' => $p[0], 'duration_type' => $p[1], 'duration_days' => $p[1] === 'custom' ? 7 : null, 'price' => $p[2],
                    'description' => 'Full gym access with trainer guidance', 'features' => ['Full gym access', 'Cardio & strength zones', 'Trainer guidance', 'Locker'],
                    'is_active' => true, 'is_public' => true, 'sort_order' => $i,
                ]));

            $trainerService = app(TrainerService::class);
            $trainers = collect([
                ['Rajat Chauhan', 'rajat.trainer@xtreme.test', 'Bodybuilding & strength', '9829200001'],
                ['Sneha Rathore', 'sneha.trainer@xtreme.test', 'Fat loss & functional training', '9829200002'],
            ])->map(fn ($t) => $trainerService->create(['name' => $t[0], 'email' => $t[1], 'password' => 'password', 'specialization' => $t[2], 'phone' => $t[3]]));

            $memberService = app(MemberService::class);
            $memberships = app(MembershipService::class);
            $names = ['Arjun Shekhawat', 'Isha Malhotra', 'Vivek Sharma', 'Ananya Singh', 'Rahul Verma', 'Pooja Meena', 'Kunal Agarwal',
                'Sakshi Jain', 'Amit Choudhary', 'Nidhi Sharma', 'Vishal Saini', 'Riya Khandelwal', 'Mohit Rajawat', 'Kritika Gupta',
                'Siddharth Joshi', 'Aarti Yadav', 'Pranav Mathur', 'Shivani Bhatt', 'Devendra Singh', 'Payal Soni', 'Naveen Kumawat', 'Tanya Bansal'];
            $goals = ['Weight loss', 'Muscle gain', 'General fitness', 'Strength training'];

            foreach ($names as $i => $name) {
                $joined = Carbon::today()->subDays(rand(15, 240));
                $member = $memberService->create([
                    'name' => $name, 'phone' => '79000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'email' => match ($i) {
                        0 => 'member@xtreme.test', 1 => 'member2@xtreme.test', default => null
                    },
                    'gender' => $i % 2 ? 'female' : 'male', 'joining_date' => $joined->toDateString(), 'city' => 'Jaipur',
                    'date_of_birth' => Carbon::today()->subYears(rand(18, 42))->subDays(rand(0, 360))->toDateString(),
                    'fitness_goal' => $goals[$i % 4], 'status' => $i >= 20 ? 'inactive' : 'active',
                ], ['trainer_id' => $trainers[$i % 2]->id, 'portal_password' => $i <= 1 ? 'password' : null]);

                $plan = $plans[$i % 4];
                // A mix of active, expiring-soon and expired memberships.
                $start = match (true) {
                    $i % 7 === 3 => Carbon::today()->subMonths(8),
                    $i % 5 === 0 => Carbon::today()->subDays(25),
                    default => $joined->copy(),
                };
                $discount = $i % 4 === 1 ? 250 : 0;
                $ms = $memberships->assign($member, $plan, $start->toDateString(), $discount, null, [
                    // Every third member has paid only part of the fee (dues pending).
                    'amount' => $i % 3 === 2 ? round(((float) $plan->price - $discount) * 0.6) : (float) $plan->price - $discount,
                    'method' => ['upi', 'cash', 'card', 'bank_transfer'][$i % 4], 'paid_at' => $start->copy()->setTime(11, 0)->toDateTimeString(),
                ]);

                // Attendance for the last 30 days (closed on Sundays).
                foreach (range(0, 29) as $d) {
                    $day = Carbon::today()->subDays($d);
                    if ($day->isSunday() || $day->lt($ms->start_date) || $day->gt($ms->end_date) || rand(0, 2) === 0) {
                        continue;
                    }
                    $in = $day->copy()->setTime(rand(6, 20), rand(0, 59));
                    $a = new Attendance;
                    $a->member_id = $member->id;
                    $a->date = $day->toDateString();
                    $a->check_in_at = $in;
                    $a->check_out_at = $d === 0 && rand(0, 1) ? null : $in->copy()->addMinutes(rand(45, 120));
                    $a->method = rand(0, 1) ? 'qr' : 'manual';
                    $a->recorded_by = $ownerId;
                    $a->save();
                }
            }
        });

        $this->command?->info('Dummy data added to Xtreme Fitness Gym.');
    }
}
