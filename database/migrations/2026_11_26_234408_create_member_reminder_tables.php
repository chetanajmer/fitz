<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal reminders in the member app (water, diet meals, workout) and water tracking:
 *  - member_reminder_settings: one row per member, created with the defaults on first use;
 *  - member_water_logs: every glass the member logs;
 *  - member_meal_reminders: today's meal reminders, built from the member's diet program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('hydration_enabled')->default(false);
            $table->unsignedSmallInteger('hydration_interval_minutes')->default(60);
            $table->time('hydration_start')->default('08:00');
            $table->time('hydration_end')->default('21:00');
            $table->unsignedSmallInteger('hydration_goal_ml')->default(2500);
            $table->unsignedSmallInteger('glass_ml')->default(250);
            $table->dateTime('hydration_next_at')->nullable()->index();
            $table->date('hydration_goal_met_on')->nullable(); // the day the goal was last reached (one celebration a day)

            $table->boolean('diet_enabled')->default(false);
            $table->unsignedSmallInteger('diet_lead_minutes')->default(0);

            $table->boolean('workout_enabled')->default(false);
            $table->time('workout_time')->default('18:00');
            $table->boolean('workout_skip_rest_days')->default(true);
            $table->dateTime('workout_next_at')->nullable()->index();

            $table->timestamps();
        });

        Schema::create('member_water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('ml');
            $table->dateTime('logged_at');
            $table->string('source', 10)->default('app'); // app | push
            $table->timestamps();

            $table->index(['member_id', 'date']);
        });

        Schema::create('member_meal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('plan_section_id')->constrained('fitness_plan_sections')->cascadeOnDelete();
            $table->string('title', 150);
            $table->time('meal_time');
            $table->dateTime('send_at');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'date', 'plan_section_id']);
            $table->index(['send_at', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_meal_reminders');
        Schema::dropIfExists('member_water_logs');
        Schema::dropIfExists('member_reminder_settings');
    }
};
