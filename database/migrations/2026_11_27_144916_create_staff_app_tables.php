<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff mobile app (gym admins and trainers), kept apart from the member app tables:
 *  - staff_devices: Firebase (FCM) tokens of the phones a staff user is signed in on;
 *  - idempotency_keys: responses of create requests sent with an Idempotency-Key header (kept 24 hours);
 *  - staff_deletion_requests: account deletion requests sent from the staff app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('access_token_id')->nullable()->index();
            $table->string('token', 512)->unique();
            $table->string('platform', 10);
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('key');
            $table->string('route', 191);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['user_id', 'key']);
        });

        Schema::create('staff_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deletion_requests');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('staff_devices');
    }
};
