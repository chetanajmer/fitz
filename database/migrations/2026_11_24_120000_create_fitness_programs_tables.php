<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programs: a master day-by-day schedule (workout or diet) built from the gym's plans.
 * Assigning a program to a member gives them the right plan for every day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('diet_preference', 12)->nullable()->after('fitness_goal'); // veg | eggetarian | non_veg | vegan
        });

        Schema::create('fitness_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // diet | workout
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('goal', 30);
            $table->string('level', 20)->nullable();           // workout: beginner | intermediate | advanced
            $table->string('diet_preference', 12)->nullable(); // diet: veg | eggetarian | non_veg | vegan
            $table->unsignedSmallInteger('duration_weeks')->default(4);
            $table->string('library_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'type', 'goal']);
            $table->unique(['gym_id', 'library_key']);
        });

        Schema::create('fitness_program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fitness_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number'); // 1..cycle length (the cycle repeats)
            $table->string('title')->nullable();
            $table->boolean('is_rest')->default(false);
            $table->foreignId('fitness_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['fitness_program_id', 'day_number']);
        });

        Schema::create('member_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fitness_program_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 12)->default('active'); // active | stopped
            $table->string('notes', 500)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type', 'status']);
            $table->index(['gym_id', 'fitness_program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_programs');
        Schema::dropIfExists('fitness_program_days');
        Schema::dropIfExists('fitness_programs');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('diet_preference'));
    }
};
