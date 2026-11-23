<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('duration_type', 20); // monthly, quarterly, half_yearly, yearly, custom
            $table->unsignedInteger('duration_days')->nullable(); // used when duration_type = custom
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true); // shown on public gym profile
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
