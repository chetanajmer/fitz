<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Per-gym custom price per active member. The rate for a billing period is the gym's
         * override covering the period start, otherwise the platform rate (pricing_rates).
         */
        Schema::create('gym_pricing_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_per_member', 10, 2);
            $table->date('effective_from');
            $table->date('ends_on')->nullable(); // null = open-ended
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gym_id', 'effective_from']);
        });

        /*
         * Super Admin "login as" magic links: single use, short-lived, audited.
         * Only a SHA-256 hash of the token is stored.
         */
        Schema::create('impersonation_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();        // account to open
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // super admin
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->string('used_user_agent')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_tokens');
        Schema::dropIfExists('gym_pricing_overrides');
    }
};
