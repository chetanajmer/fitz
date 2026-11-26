<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freezing (pausing) a membership: each plan allows a number of freeze days; a freeze moves the membership's
 * end date out by its length. Recorded by staff only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_freeze_days')->default(0)->after('duration_days'); // 0 = no freezing
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_freeze_days')->default(0)->after('duration_days'); // copied from the plan when assigned
        });

        Schema::create('membership_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['membership_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_freezes');
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn('max_freeze_days'));
        Schema::table('membership_plans', fn (Blueprint $table) => $table->dropColumn('max_freeze_days'));
    }
};
