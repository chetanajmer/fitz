<?php

use App\Support\NotificationCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable push notifications:
 *  - notification_templates: wording, default time and rules of each automatic notification (Super Admin only);
 *  - gym_push_runs: which scheduled notification already ran for a gym on a day;
 *  - announcements: custom gym notifications must be approved by the Super Admin before they can be sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('title', 100);
            $table->string('body', 300);
            $table->boolean('is_enabled')->default(true);
            $table->string('send_time', 5)->nullable(); // HH:MM, scheduled notifications only
            $table->json('params')->nullable();          // e.g. {"days":[7,3,1]}
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        foreach (NotificationCatalog::TYPES as $key => $definition) {
            DB::table('notification_templates')->insert([
                'key' => $key,
                'title' => $definition['title'],
                'body' => $definition['body'],
                'is_enabled' => true,
                'send_time' => $definition['time'] ?? null,
                'params' => json_encode(NotificationCatalog::defaultParams($key)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('gym_push_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->date('run_on');
            $table->timestamp('created_at')->nullable();

            $table->unique(['gym_id', 'type', 'run_on']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('rejection_reason', 500)->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'rejection_reason']);
        });
        Schema::dropIfExists('gym_push_runs');
        Schema::dropIfExists('notification_templates');
    }
};
