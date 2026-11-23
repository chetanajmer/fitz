<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diet charts and workout plans share one structure, distinguished by `type`.
     * A plan with member_id = null is a default plan (all members); with a member_id it is
     * a customised plan visible only to that member.
     */
    public function up(): void
    {
        Schema::create('plan_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // diet | workout
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['gym_id', 'type', 'is_active']);
        });

        Schema::create('fitness_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // diet | workout
            $table->foreignId('plan_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->cascadeOnDelete(); // null = default plan
            $table->foreignId('copied_from_id')->nullable()->constrained('fitness_plans')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('level', 20)->nullable();          // beginner | intermediate | advanced
            $table->unsignedSmallInteger('duration_weeks')->nullable();
            $table->unsignedInteger('daily_calories')->nullable(); // diet charts
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'type', 'member_id']);
            $table->index(['gym_id', 'plan_category_id']);
        });

        Schema::create('fitness_plan_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fitness_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable(); // e.g. "8:00 AM" or "4 sets × 12 reps"
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitness_plan_sections');
        Schema::dropIfExists('fitness_plans');
        Schema::dropIfExists('plan_categories');
    }
};
