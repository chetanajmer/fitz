<?php

namespace Database\Seeders;

use App\Enums\EnquirySource;
use App\Enums\EnquiryStatus;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\GymRegistrationService;
use App\Services\MemberService;
use App\Services\MembershipService;
use App\Services\TrainerService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo gym with plans, trainers, members, payments, enquiries and attendance.
 * Login: owner@demo.gymai.test / password · trainer@demo.gymai.test / password · member@demo.gymai.test / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        if (User::where('email', 'owner@demo.gymai.test')->exists()) {
            $this->command?->warn('Demo data already present.');

            return;
        }

        $owner = app(GymRegistrationService::class)->register([
            'owner_name' => 'Arjun Mehta', 'email' => 'owner@demo.gymai.test', 'phone' => '9876500001', 'password' => 'password',
            'gym_name' => 'Iron Temple Fitness', 'gym_phone' => '9876500000', 'city' => 'Pune', 'address' => '21 FC Road, Shivajinagar',
        ], login: false);

        $gym = Gym::find($owner->gym_id);
        $gym->description = "Pune's friendliest strength and conditioning gym. Certified coaches, modern equipment and a community that keeps you coming back.";
        $gym->settings = array_replace($gym->allSettings(), [
            'tagline' => 'Stronger every day',
            'facilities' => ['Free weights & racks', 'Cardio zone', 'Personal training', 'Steam room', 'Locker rooms', 'Parking'],
            'social' => ['website' => null, 'instagram' => 'https://instagram.com/', 'facebook' => null, 'youtube' => null, 'whatsapp' => '919876500000'],
        ]);
        $gym->save();

        app(TenantManager::class)->runAs($gym, function () use ($owner) {
            $plans = collect([
                ['Monthly', 'monthly', 1500], ['Quarterly', 'quarterly', 4000], ['Half-yearly', 'half_yearly', 7000],
                ['Yearly', 'yearly', 12000], ['20-day Trial', 'custom', 800],
            ])->map(fn ($p, $i) => MembershipPlan::create([
                'name' => $p[0], 'duration_type' => $p[1], 'duration_days' => $p[1] === 'custom' ? 20 : null, 'price' => $p[2],
                'description' => 'Full gym access', 'features' => ['Full gym access', 'Locker'], 'is_active' => true, 'is_public' => true, 'sort_order' => $i,
            ]));

            $trainerService = app(TrainerService::class);
            $trainers = collect([
                ['Neha Kulkarni', 'trainer@demo.gymai.test', 'Strength & conditioning'],
                ['Rohit Pawar', 'rohit.trainer@demo.gymai.test', 'Weight loss'],
            ])->map(fn ($t) => $trainerService->create(['name' => $t[0], 'email' => $t[1], 'password' => 'password', 'specialization' => $t[2], 'phone' => '98765'.rand(10000, 99999)]));

            $memberService = app(MemberService::class);
            $memberships = app(MembershipService::class);
            $names = ['Aarav Shah', 'Diya Patel', 'Vihaan Rao', 'Ananya Iyer', 'Kabir Singh', 'Isha Joshi', 'Reyansh Nair', 'Saanvi Gupta',
                'Arnav Desai', 'Myra Reddy', 'Advik Menon', 'Kiara Bose', 'Ishaan Verma', 'Anika Chawla', 'Dhruv Malhotra', 'Pari Kapoor',
                'Atharv Jain', 'Navya Sinha', 'Vivaan Das', 'Riya Bhatt'];

            foreach ($names as $i => $name) {
                $joined = Carbon::today()->subDays(rand(5, 170));
                $member = $memberService->create([
                    'name' => $name, 'phone' => '90000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'email' => $i === 0 ? 'member@demo.gymai.test' : null,
                    'gender' => $i % 2 ? 'female' : 'male', 'joining_date' => $joined->toDateString(), 'city' => 'Pune',
                    'fitness_goal' => ['Weight loss', 'Muscle gain', 'General fitness'][$i % 3], 'status' => $i === 19 ? 'inactive' : 'active',
                ], ['trainer_id' => $trainers[$i % 2]->id, 'portal_password' => $i === 0 ? 'password' : null]);

                $plan = $plans[$i % 4];
                // Mix of active, expiring soon and expired memberships.
                $start = match (true) {
                    $i % 7 === 3 => Carbon::today()->subMonths(5),
                    $i % 5 === 0 => Carbon::today()->subDays(25),
                    default => $joined->copy(),
                };
                $ms = $memberships->assign($member, $plan, $start->toDateString(), $i % 4 === 0 ? 200 : 0, null, [
                    'amount' => $i % 3 === 0 ? round((float) $plan->price / 2) : (float) $plan->price - ($i % 4 === 0 ? 200 : 0),
                    'method' => ['cash', 'upi', 'bank_transfer', 'card'][$i % 4], 'paid_at' => $start->copy()->setTime(10, 0)->toDateTimeString(),
                ]);

                // Attendance for the last 10 days.
                foreach (range(0, 9) as $d) {
                    $day = Carbon::today()->subDays($d);
                    if ($day->lt($ms->start_date) || $day->gt($ms->end_date) || rand(0, 2) === 0) {
                        continue;
                    }
                    $in = $day->copy()->setTime(rand(6, 20), rand(0, 59));
                    $a = new Attendance;
                    $a->member_id = $member->id;
                    $a->date = $day->toDateString();
                    $a->check_in_at = $in;
                    $a->check_out_at = $d === 0 && rand(0, 1) ? null : $in->copy()->addMinutes(rand(40, 110));
                    $a->method = rand(0, 1) ? 'qr' : 'manual';
                    $a->recorded_by = $owner->id;
                    $a->save();
                }
            }

            $enquiries = app(EnquiryService::class);
            foreach ([['Manish Tiwari', 'qr'], ['Sneha Pillai', 'walk_in'], ['Farhan Qureshi', 'website'], ['Lata Deshpande', 'referral'], ['Omkar Gokhale', 'phone']] as $i => $e) {
                $enq = $enquiries->create(['name' => $e[0], 'phone' => '81000'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'fitness_goal' => 'Weight loss',
                    'source' => EnquirySource::from($e[1])->value, 'interested_plan_id' => $plans[$i % 3]->id, 'follow_up_date' => Carbon::today()->addDays($i - 1)->toDateString()]);
                if ($i === 1) {
                    $enquiries->changeStatus($enq, EnquiryStatus::Contacted, 'Called, will visit on Saturday.');
                }
                if ($i === 2) {
                    $enquiries->changeStatus($enq, EnquiryStatus::Interested, 'Wants quarterly plan.');
                }
            }
        });

        $this->command?->info('Demo gym created: owner@demo.gymai.test / password');
    }
}
