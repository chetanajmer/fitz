<?php

namespace Database\Seeders;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\EnquiryService;
use App\Services\GymRegistrationService;
use App\Services\MemberService;
use App\Services\MembershipService;
use App\Services\TrainerService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dummy plans, trainers, members, payments, attendance and enquiries for the "fitLife gym jagatpura" gym.
 * php artisan db:seed --class=FitLifeDemoSeeder
 * Creates the gym (owner@fitlife.test / password) if it does not exist yet.
 * Trainer logins: *.trainer@fitlife.test / password · Member portal: member@fitlife.test / password
 */
class FitLifeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::where('slug', 'fitlife-gym-jagatpura')->first();

        if (! $gym) {
            $owner = app(GymRegistrationService::class)->register([
                'owner_name' => 'Naresh Arya', 'email' => 'owner@fitlife.test', 'phone' => '9024235434', 'password' => 'password',
                'gym_name' => 'fitLife gym jagatpura', 'gym_phone' => '9024235434', 'city' => 'Jaipur', 'address' => 'Jagatpura, Jaipur',
            ], login: false);
            $gym = Gym::find($owner->gym_id);
        }

        if (Member::withoutGlobalScopes()->where('gym_id', $gym->id)->exists()) {
            $this->command?->warn('fitLife gym already has members — skipping.');

            return;
        }

        $gym->description = 'Jagatpura ka modern fitness centre — certified trainers, cardio & strength zones, aur friendly community.';
        $gym->settings = array_replace($gym->allSettings(), [
            'tagline' => 'Fit raho, Life jiyo',
            'facilities' => ['Free weights & racks', 'Cardio zone', 'Personal training', 'Zumba & aerobics', 'Locker rooms', 'Parking'],
        ]);
        $gym->save();

        $ownerId = $gym->owner_id;

        app(TenantManager::class)->runAs($gym, function () use ($ownerId) {
            $plans = collect([
                ['Monthly', 'monthly', 1200], ['Quarterly', 'quarterly', 3300], ['Half-yearly', 'half_yearly', 6000],
                ['Yearly', 'yearly', 10000], ['15-day Trial', 'custom', 500],
            ])->map(fn ($p, $i) => MembershipPlan::create([
                'name' => $p[0], 'duration_type' => $p[1], 'duration_days' => $p[1] === 'custom' ? 15 : null, 'price' => $p[2],
                'description' => 'Full gym access', 'features' => ['Full gym access', 'Locker', 'Cardio zone'], 'is_active' => true, 'is_public' => true, 'sort_order' => $i,
            ]));

            $trainerService = app(TrainerService::class);
            $trainers = collect([
                ['Vikram Shekhawat', 'vikram.trainer@fitlife.test', 'Strength & powerlifting', '9829100001'],
                ['Pooja Sharma', 'pooja.trainer@fitlife.test', 'Weight loss & Zumba', '9829100002'],
                ['Rahul Meena', 'rahul.trainer@fitlife.test', 'Functional training', '9829100003'],
            ])->map(fn ($t) => $trainerService->create(['name' => $t[0], 'email' => $t[1], 'password' => 'password', 'specialization' => $t[2], 'phone' => $t[3]]));

            $memberService = app(MemberService::class);
            $memberships = app(MembershipService::class);
            $names = ['Aditya Rathore', 'Priya Agarwal', 'Manish Saini', 'Kavya Jain', 'Rohit Choudhary', 'Neha Khandelwal', 'Sumit Yadav',
                'Ritu Sharma', 'Deepak Gurjar', 'Anjali Mathur', 'Harsh Vijay', 'Simran Kaur', 'Karan Singhvi', 'Megha Goyal', 'Yash Bansal',
                'Tanvi Mittal', 'Lokesh Kumawat', 'Shruti Purohit', 'Nikhil Soni', 'Pallavi Joshi', 'Gaurav Tak', 'Divya Bhargava',
                'Ajay Bairwa', 'Komal Gupta', 'Sachin Poonia'];

            foreach ($names as $i => $name) {
                $joined = Carbon::today()->subDays(rand(10, 200));
                $member = $memberService->create([
                    'name' => $name, 'phone' => '78000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'email' => $i === 0 ? 'member@fitlife.test' : null,
                    'gender' => $i % 2 ? 'female' : 'male', 'joining_date' => $joined->toDateString(), 'city' => 'Jaipur',
                    'fitness_goal' => ['Weight loss', 'Muscle gain', 'General fitness'][$i % 3], 'status' => $i >= 23 ? 'inactive' : 'active',
                ], ['trainer_id' => $trainers[$i % 3]->id, 'portal_password' => $i === 0 ? 'password' : null]);

                $plan = $plans[$i % 4];
                // Mix of active, expiring-soon and expired memberships.
                $start = match (true) {
                    $i % 6 === 2 => Carbon::today()->subMonths(7),
                    $i % 5 === 0 => Carbon::today()->subDays(26),
                    default => $joined->copy(),
                };
                $discount = $i % 4 === 0 ? 200 : 0;
                $ms = $memberships->assign($member, $plan, $start->toDateString(), $discount, null, [
                    // Every third member has paid only half (dues pending).
                    'amount' => $i % 3 === 0 ? round(((float) $plan->price - $discount) / 2) : (float) $plan->price - $discount,
                    'method' => ['cash', 'upi', 'bank_transfer', 'card'][$i % 4], 'paid_at' => $start->copy()->setTime(10, 0)->toDateTimeString(),
                ]);

                // Attendance for the last 30 days.
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
                    $a->check_out_at = $d === 0 && rand(0, 1) ? null : $in->copy()->addMinutes(rand(40, 110));
                    $a->method = rand(0, 1) ? 'qr' : 'manual';
                    $a->recorded_by = $ownerId;
                    $a->save();
                }
            }

            $enquiries = app(EnquiryService::class);
            $leads = [['Mohit Saxena', 'walk_in'], ['Nisha Rawat', 'qr'], ['Arjun Shekhawat', 'website'], ['Swati Kothari', 'referral'],
                ['Ravi Prajapat', 'phone'], ['Bhavna Tiwari', 'walk_in'], ['Abhishek Dhaka', 'website']];
            foreach ($leads as $i => $e) {
                $enq = $enquiries->create(['name' => $e[0], 'phone' => '76000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'fitness_goal' => ['Weight loss', 'Muscle gain'][$i % 2],
                    'source' => EnquirySource::from($e[1])->value, 'interested_plan_id' => $plans[$i % 3]->id, 'follow_up_date' => Carbon::today()->addDays($i - 2)->toDateString()]);
                if (in_array($i, [1, 4], true)) {
                    $enquiries->changeStatus($enq, EnquiryStatus::Contacted, 'Call kiya, weekend par visit karenge.');
                }
                if (in_array($i, [2, 5], true)) {
                    $enquiries->changeStatus($enq, EnquiryStatus::Interested, 'Quarterly plan mein interest hai.');
                }
            }
        });

        $this->command?->info('Dummy data added to fitLife gym jagatpura.');
    }
}
