<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Push notifications for the member app:
 *  - member_devices: Firebase (FCM) tokens of the phones a member is signed in on;
 *  - announcements: messages a gym sends to a group of its members;
 *  - member_notifications: every notification a member received (the in-app inbox);
 *  - member_push_logs: automatic pushes already sent (member + type + ref), never sent twice;
 *  - members.notification_settings: which optional notifications the member wants;
 *  - new permission announcements.send (gym admins; grantable to trainers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 10); // android | ios
            $table->string('app_version', 20)->nullable();
            $table->string('device_name', 100)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'member_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 100);
            $table->string('body', 1000);
            $table->string('image_path')->nullable();
            $table->string('audience', 20); // all | active | expiring | expired | trainer
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('scheduled'); // scheduled | sending | sent | cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('pushed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['gym_id', 'created_at']);
        });

        Schema::create('member_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 150);
            $table->string('body', 1000);
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'read_at']);
            $table->index(['member_id', 'type', 'created_at']);
        });

        // One row per automatic push already sent, so a scheduled push never goes out twice.
        Schema::create('member_push_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('ref', 100);
            $table->timestamp('sent_at');

            $table->unique(['member_id', 'type', 'ref']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('diet_preference');
        });

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('notification_settings'));
        Schema::dropIfExists('member_push_logs');
        Schema::dropIfExists('member_notifications');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('member_devices');
    }
};
