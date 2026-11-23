<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored streak snapshot per member. Attendance remains the source of truth: the
     * snapshot is recalculated on every check-in, nightly, and on demand.
     */
    public function up(): void
    {
        Schema::create('member_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_streak')->default(0);   // visit days in the live streak
            $table->date('current_started_on')->nullable();
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('longest_started_on')->nullable();
            $table->date('longest_ended_on')->nullable();
            $table->date('last_visit_on')->nullable();
            $table->unsignedInteger('total_visit_days')->default(0);
            $table->unsignedInteger('highest_milestone')->default(0); // best milestone ever reached
            $table->timestamp('milestone_reached_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'current_streak']);
            $table->index(['gym_id', 'longest_streak']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_streaks');
    }
};
