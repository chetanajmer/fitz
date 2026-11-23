<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds categories and plans that are new in database/data/fitness-library.php (e.g. eggetarian,
 * vegan and lean diet charts) to the editable default library. Matched by key, so anything the
 * Super Admin already has — including edits — is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $library = require database_path('data/fitness-library.php');
        $now = now();

        foreach (['diet', 'workout'] as $type) {
            foreach ($library[$type]['categories'] ?? [] as $ci => $category) {
                $categoryId = DB::table('library_categories')->where('key', $category['key'])->value('id')
                    ?? DB::table('library_categories')->insertGetId([
                        'key' => $category['key'], 'type' => $type, 'name' => $category['name'],
                        'description' => $category['description'] ?? null,
                        'sort_order' => (int) DB::table('library_categories')->where('type', $type)->max('sort_order') + 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);

                foreach ($category['plans'] as $pi => $plan) {
                    if (DB::table('library_plans')->where('key', $plan['key'])->exists()) {
                        continue;
                    }

                    $planId = DB::table('library_plans')->insertGetId([
                        'key' => $plan['key'], 'library_category_id' => $categoryId, 'type' => $type,
                        'title' => $plan['title'], 'summary' => $plan['summary'] ?? null, 'level' => $plan['level'] ?? null,
                        'duration_weeks' => $plan['duration_weeks'] ?? null,
                        'daily_calories' => $type === 'diet' ? ($plan['daily_calories'] ?? null) : null,
                        'is_published' => true,
                        'sort_order' => (int) DB::table('library_plans')->where('library_category_id', $categoryId)->max('sort_order') + 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);

                    foreach ($plan['sections'] as $si => $section) {
                        DB::table('library_sections')->insert([
                            'library_plan_id' => $planId, 'title' => $section[0], 'subtitle' => $section[1] ?? null,
                            'body' => $section[2] ?? null, 'sort_order' => $si, 'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Content migration: nothing to undo safely (the Super Admin may have edited these items).
    }
};
