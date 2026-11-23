<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('height_cm', 5, 1)->nullable()->after('blood_group');
        });

        Schema::create('member_weight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight_kg', 5, 2);
            $table->date('measured_on');
            $table->string('source', 10)->default('app'); // app | staff
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'measured_on']); // one reading per day (re-entry updates it)
            $table->index(['gym_id', 'measured_on']);
        });

        Schema::create('member_weight_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->decimal('start_weight_kg', 5, 2);
            $table->decimal('target_weight_kg', 5, 2);
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->string('status', 12)->default('active'); // active | achieved | replaced
            $table->timestamp('achieved_at')->nullable();
            $table->decimal('achieved_weight_kg', 5, 2)->nullable();
            $table->timestamp('celebrated_at')->nullable(); // member saw the "target achieved" popup
            $table->timestamp('near_notified_at')->nullable(); // member saw the "almost there" popup
            $table->string('set_by', 10)->default('app'); // app | staff
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['gym_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_weight_goals');
        Schema::dropIfExists('member_weight_logs');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('height_cm'));
    }
};
