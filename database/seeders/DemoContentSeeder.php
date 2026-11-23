<?php

namespace Database\Seeders;

use App\Enums\FitnessPlanType;
use App\Models\FitnessPlan;
use App\Models\Gym;
use App\Models\Member;
use App\Models\PlanCategory;
use App\Services\FitnessPlanService;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Sample diet & workout categories/plans for the demo gym (text only — add photos from the panel).
 * php artisan db:seed --class=DemoContentSeeder
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::where('slug', 'iron-temple-fitness')->first();

        if (! $gym) {
            $this->command?->warn('Demo gym not found — run DemoSeeder first.');

            return;
        }

        app(TenantManager::class)->runAs($gym, function () {
            if (FitnessPlan::exists()) {
                $this->command?->warn('Demo content already present.');

                return;
            }

            $service = app(FitnessPlanService::class);

            $weightLoss = PlanCategory::firstOrCreate(['type' => 'diet', 'name' => 'Weight Loss'], ['description' => 'Calorie-controlled, high-protein charts', 'sort_order' => 1]);
            $muscleGain = PlanCategory::firstOrCreate(['type' => 'diet', 'name' => 'Muscle Gain'], ['description' => 'Calorie surplus with 1.6–2 g protein per kg', 'sort_order' => 2]);
            $chest = PlanCategory::firstOrCreate(['type' => 'workout', 'name' => 'Push (Chest · Shoulders · Triceps)'], ['sort_order' => 1]);
            $legs = PlanCategory::firstOrCreate(['type' => 'workout', 'name' => 'Legs'], ['sort_order' => 2]);

            $service->savePlan(null, FitnessPlanType::Diet, ['title' => '1500 kcal vegetarian fat loss', 'summary' => 'Simple Indian vegetarian meals, ~110 g protein a day.', 'plan_category_id' => $weightLoss->id, 'daily_calories' => 1500, 'level' => 'beginner', 'duration_weeks' => 8], [
                ['title' => 'Early morning', 'subtitle' => '6:30 AM', 'body' => "1 glass warm water with lemon\n5 soaked almonds"],
                ['title' => 'Breakfast', 'subtitle' => '8:30 AM · 350 kcal', 'body' => "Moong dal chilla (2) with mint chutney\n1 cup green tea"],
                ['title' => 'Lunch', 'subtitle' => '1:00 PM · 450 kcal', 'body' => "2 phulka, 1 bowl dal, 1 bowl sabzi\nSalad + 1 bowl curd"],
                ['title' => 'Evening snack', 'subtitle' => '5:00 PM · 200 kcal', 'body' => "Roasted chana (30 g)\n1 fruit"],
                ['title' => 'Dinner', 'subtitle' => '8:00 PM · 450 kcal', 'body' => "Paneer bhurji (100 g paneer)\n1 phulka + sautéed vegetables"],
            ]);
            $service->savePlan(null, FitnessPlanType::Diet, ['title' => '2800 kcal lean bulk', 'summary' => 'Higher carbs around training, 150 g protein.', 'plan_category_id' => $muscleGain->id, 'daily_calories' => 2800, 'level' => 'intermediate'], [
                ['title' => 'Breakfast', 'subtitle' => '8:00 AM', 'body' => "4 egg whites + 2 whole eggs\n80 g oats with milk and banana"],
                ['title' => 'Pre-workout', 'subtitle' => '5:00 PM', 'body' => 'Peanut butter toast (2 slices) + black coffee'],
                ['title' => 'Post-workout', 'subtitle' => '7:30 PM', 'body' => "1 scoop whey\n150 g rice + 150 g chicken"],
            ]);
            $service->savePlan(null, FitnessPlanType::Workout, ['title' => 'Push day — beginner', 'summary' => 'Chest, shoulders and triceps. ~50 minutes.', 'plan_category_id' => $chest->id, 'level' => 'beginner'], [
                ['title' => 'Warm-up', 'subtitle' => '8 minutes', 'body' => 'Treadmill walk 5 min, arm circles, band pull-aparts.'],
                ['title' => 'Flat bench press', 'subtitle' => '4 sets × 10 reps · rest 90s', 'body' => 'Shoulder blades squeezed, bar to mid-chest, feet flat.'],
                ['title' => 'Seated dumbbell shoulder press', 'subtitle' => '3 × 12 · rest 60s', 'body' => 'Do not lock elbows at the top.'],
                ['title' => 'Cable triceps pushdown', 'subtitle' => '3 × 15', 'body' => 'Elbows pinned to your sides.'],
            ]);
            $service->savePlan(null, FitnessPlanType::Workout, ['title' => 'Leg day', 'summary' => 'Quads, hamstrings, glutes and calves.', 'plan_category_id' => $legs->id, 'level' => 'intermediate'], [
                ['title' => 'Back squat', 'subtitle' => '5 × 5 · rest 2–3 min', 'body' => 'Brace your core, hips back and down, knees track over toes.'],
                ['title' => 'Romanian deadlift', 'subtitle' => '3 × 10', 'body' => 'Soft knees, push hips back, bar close to legs.'],
                ['title' => 'Walking lunges', 'subtitle' => '3 × 12 each leg', 'body' => ''],
                ['title' => 'Standing calf raise', 'subtitle' => '4 × 15', 'body' => 'Pause at the top.'],
            ]);

            // A customised chart for the demo member.
            if ($member = Member::where('email', 'member@demo.gymai.test')->first()) {
                $service->savePlan(null, FitnessPlanType::Diet, ['title' => 'Aarav — personal cut plan', 'summary' => 'Adjusted for your lactose intolerance. Review in 4 weeks.', 'plan_category_id' => $weightLoss->id, 'daily_calories' => 1700], [
                    ['title' => 'Breakfast', 'subtitle' => '8:00 AM', 'body' => "3 egg omelette with spinach\n2 slices multigrain toast"],
                    ['title' => 'Lunch', 'subtitle' => '1:30 PM', 'body' => 'Brown rice (1 cup), rajma, cucumber salad'],
                    ['title' => 'Dinner', 'subtitle' => '8:00 PM', 'body' => "Grilled chicken (150 g), stir-fried veggies\nNo dairy"],
                ], $member);
            }

            $this->command?->info('Demo diet charts & workout plans created.');
        });
    }
}
