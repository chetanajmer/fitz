<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level leads: people who tried to log in to the member app but are not an
     * active member of any active gym. Visible to the Super Admin only (no gym_id scope).
     */
    public function up(): void
    {
        Schema::create('platform_leads', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('phone_digits', 15)->index();
            $table->string('name')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source', 30)->default('member_app');
            // not_registered | member_inactive | member_removed | gym_inactive
            $table->string('reason', 30);
            // Gym the number was previously linked to, if any (context for the support team).
            $table->foreignId('previous_gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->foreignId('assigned_gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->string('status', 20)->default('new'); // new, contacted, follow_up, converted, not_interested, closed
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('request_count')->default(1);
            $table->timestamp('last_requested_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('app_platform', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_leads');
    }
};
