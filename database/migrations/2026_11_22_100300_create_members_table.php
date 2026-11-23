<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // member portal login
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('enquiry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('member_code', 30);
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->date('joining_date');
            $table->string('fitness_goal')->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active'); // active, inactive
            $table->string('qr_token', 64)->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gym_id', 'sequence']);
            $table->unique(['gym_id', 'member_code']);
            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'phone']);
            $table->index(['gym_id', 'trainer_id']);
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreign('converted_member_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropForeign(['converted_member_id']));
        Schema::dropIfExists('members');
    }
};
