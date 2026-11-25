<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localities (areas) inside a city, so members can find their gym in big cities.
 * Gyms pick one from their city's list or add a new one; the Super Admin can rename or remove them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['city_id', 'name']);
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->foreignId('locality_id')->nullable()->after('city_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gyms', fn (Blueprint $table) => $table->dropConstrainedForeignId('locality_id'));
        Schema::dropIfExists('localities');
    }
};
