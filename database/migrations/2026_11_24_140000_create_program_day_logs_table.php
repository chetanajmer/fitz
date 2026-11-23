<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per member, program type and day: did they follow that day's workout / diet?
     */
    public function up(): void
    {
        Schema::create('program_day_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_program_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // workout | diet
            $table->date('date');
            $table->string('status', 12); // done | skipped
            $table->unsignedSmallInteger('program_day');
            $table->foreignId('fitness_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150); // the day's title at the time, kept even if the program changes
            $table->string('note', 500)->nullable();
            $table->string('logged_by', 10)->default('member'); // member | staff
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'type', 'date']);
            $table->index(['member_program_id', 'status']);
            $table->index(['gym_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_day_logs');
    }
};
