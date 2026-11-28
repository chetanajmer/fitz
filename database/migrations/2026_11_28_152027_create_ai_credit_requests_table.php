<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests for extra AI credits. With member_id: a member asks their gym admin. Without: the gym admin asks
 * the Super Admin for a bigger pool. Approved credits are added on top of the limits for the month approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('credits');
            $table->string('note', 300)->nullable();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->unsignedInteger('granted_credits')->nullable();
            $table->date('applies_month')->nullable(); // first day of the month the grant counts in
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_requests');
    }
};
