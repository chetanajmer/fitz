<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('check_in_at');
            $table->dateTime('check_out_at')->nullable();
            $table->string('method', 20)->default('manual'); // manual, qr
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Database-level duplicate check-in prevention: one attendance row per member per day.
            $table->unique(['member_id', 'date']);
            $table->index(['gym_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
