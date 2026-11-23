<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('fitness_goal')->nullable();
            $table->foreignId('interested_plan_id')->nullable()->constrained('membership_plans')->nullOnDelete();
            $table->string('source', 30)->default('walk_in');
            $table->string('status', 30)->default('new');
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            // Unique: an enquiry can be converted into at most one member.
            $table->unsignedBigInteger('converted_member_id')->nullable()->unique();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'follow_up_date']);
            $table->index(['gym_id', 'phone']);
        });

        Schema::create('enquiry_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // created, status_changed, note, converted
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_activities');
        Schema::dropIfExists('enquiries');
    }
};
