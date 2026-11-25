<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI progress module: body measurements, progress photos, AI reports (also the usage ledger)
 * and the per-gym AI limits set by the Super Admin and the gym admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained()->cascadeOnDelete();
            // Set by the Super Admin.
            $table->boolean('allowed')->default(false);
            $table->unsignedInteger('monthly_credits')->default(0); // the gym's monthly pool
            $table->unsignedSmallInteger('member_ceiling')->default(4); // max credits per member per month
            $table->boolean('photos_allowed')->default(true);
            // Set by the gym admin.
            $table->boolean('enabled')->default(false);
            $table->string('eligibility', 10)->default('all'); // all | plans | selected
            $table->json('eligible_plan_ids')->nullable();
            $table->unsignedSmallInteger('member_limit')->default(4);
            $table->boolean('auto_monthly_report')->default(false);
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('ai_access')->default(false)->after('status'); // used when eligibility = selected
            $table->timestamp('ai_consent_at')->nullable()->after('ai_access');
        });

        Schema::create('member_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('hips_cm', 5, 1)->nullable();
            $table->decimal('arm_cm', 5, 1)->nullable();
            $table->decimal('thigh_cm', 5, 1)->nullable();
            $table->decimal('body_fat_percent', 4, 1)->nullable();
            $table->string('source', 10)->default('app'); // app | staff
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'measured_on']); // one set per day (re-entry updates it)
        });

        Schema::create('member_progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->string('pose', 10); // front | side | back
            $table->string('path'); // private "local" disk
            $table->string('source', 10)->default('app'); // app | staff
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'taken_on']);
        });

        Schema::create('ai_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // progress | photo_comparison
            $table->string('status', 10)->default('pending'); // pending | completed | failed
            $table->unsignedTinyInteger('credits');
            $table->foreignId('before_photo_id')->nullable()->constrained('member_progress_photos')->nullOnDelete();
            $table->foreignId('after_photo_id')->nullable()->constrained('member_progress_photos')->nullOnDelete();
            $table->longText('content')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('error')->nullable();
            $table->string('requested_by', 10); // member | staff | auto
            $table->foreignId('requested_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'created_at']);
            $table->index(['member_id', 'created_at']);
        });

        // New permissions: ai.manage / ai.view for gym admins (trainers get ai.view per trainer).
        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reports');
        Schema::dropIfExists('member_progress_photos');
        Schema::dropIfExists('member_measurements');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn(['ai_access', 'ai_consent_at']));
        Schema::dropIfExists('gym_ai_settings');
    }
};
