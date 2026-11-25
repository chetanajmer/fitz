<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cities Gymai operates in, managed by the Super Admin. Gyms point at one city;
 * the gyms.city text column is kept in sync with the city's name for display.
 * Existing gyms are matched to cities created from their typed city names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('state', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('city')->constrained()->nullOnDelete();
        });

        $now = now();
        $cityIds = [];

        DB::table('gyms')->whereNotNull('city')->orderBy('id')->get(['id', 'city'])->each(function (object $gym) use (&$cityIds, $now) {
            $name = trim((string) $gym->city);
            if ($name === '') {
                return;
            }

            $key = mb_strtolower($name);
            $cityIds[$key] ??= DB::table('cities')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

            DB::table('gyms')->where('id', $gym->id)->update(['city_id' => $cityIds[$key], 'city' => $name]);
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('cities');
    }
};
