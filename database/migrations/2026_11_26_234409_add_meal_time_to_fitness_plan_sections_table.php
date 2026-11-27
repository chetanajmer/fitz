<?php

use App\Models\FitnessPlanSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The time of a diet-chart meal (used for meal reminders). Filled from existing subtitles
 * such as "8:30 AM · ~300 kcal"; sections without a time (guidelines, tips) stay empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fitness_plan_sections', function (Blueprint $table) {
            $table->time('meal_time')->nullable()->after('subtitle');
        });

        DB::table('fitness_plan_sections')
            ->join('fitness_plans', 'fitness_plans.id', '=', 'fitness_plan_sections.fitness_plan_id')
            ->where('fitness_plans.type', 'diet')
            ->whereNotNull('fitness_plan_sections.subtitle')
            ->select('fitness_plan_sections.id', 'fitness_plan_sections.subtitle')
            ->orderBy('fitness_plan_sections.id')
            ->chunk(500, function ($sections) {
                foreach ($sections as $section) {
                    if ($time = FitnessPlanSection::parseTime($section->subtitle)) {
                        DB::table('fitness_plan_sections')->where('id', $section->id)->update(['meal_time' => $time]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('fitness_plan_sections', function (Blueprint $table) {
            $table->dropColumn('meal_time');
        });
    }
};
