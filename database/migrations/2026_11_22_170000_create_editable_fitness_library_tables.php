<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The default fitness library moves from a PHP file into the database so the Super Admin
     * can edit it. The file (database/data/fitness-library.php) becomes the initial seed.
     * Photos move from slot-based library_images onto the rows themselves.
     */
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique(); // stable id — matches gym plan_categories.library_key
            $table->string('type', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'sort_order']);
        });

        Schema::create('library_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique(); // stable id — matches gym fitness_plans.library_key
            $table->foreignId('library_category_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('level', 20)->nullable();
            $table->unsignedSmallInteger('duration_weeks')->nullable();
            $table->unsignedInteger('daily_calories')->nullable();
            $table->boolean('is_published')->default(true); // unpublished = hidden from gym import
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('library_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedFromFile();
        $this->moveExistingPhotos();

        Schema::dropIfExists('library_images');
    }

    protected function seedFromFile(): void
    {
        $file = database_path('data/fitness-library.php');
        if (! is_file($file)) {
            return;
        }

        $library = require $file;
        $now = now();

        foreach (['diet', 'workout'] as $type) {
            foreach ($library[$type]['categories'] ?? [] as $ci => $category) {
                $categoryId = DB::table('library_categories')->insertGetId([
                    'key' => $category['key'], 'type' => $type, 'name' => $category['name'],
                    'description' => $category['description'] ?? null, 'sort_order' => $ci,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                foreach ($category['plans'] as $pi => $plan) {
                    $planId = DB::table('library_plans')->insertGetId([
                        'key' => $plan['key'], 'library_category_id' => $categoryId, 'type' => $type,
                        'title' => $plan['title'], 'summary' => $plan['summary'] ?? null, 'level' => $plan['level'] ?? null,
                        'duration_weeks' => $plan['duration_weeks'] ?? null,
                        'daily_calories' => $type === 'diet' ? ($plan['daily_calories'] ?? null) : null,
                        'is_published' => true, 'sort_order' => $pi, 'created_at' => $now, 'updated_at' => $now,
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

    /** Carry over photos uploaded with the previous slot-based screen. */
    protected function moveExistingPhotos(): void
    {
        if (! Schema::hasTable('library_images')) {
            return;
        }

        foreach (DB::table('library_images')->get() as $image) {
            if (preg_match('/^cat:(.+)$/', $image->slot, $m)) {
                DB::table('library_categories')->where('key', $m[1])->update(['image_path' => $image->path]);
            } elseif (preg_match('/^plan:(.+):cover$/', $image->slot, $m)) {
                DB::table('library_plans')->where('key', $m[1])->update(['cover_image_path' => $image->path]);
            } elseif (preg_match('/^plan:(.+):s(\d+)$/', $image->slot, $m)) {
                $planId = DB::table('library_plans')->where('key', $m[1])->value('id');
                $sectionId = $planId ? DB::table('library_sections')->where('library_plan_id', $planId)->orderBy('sort_order')->skip((int) $m[2])->value('id') : null;
                if ($sectionId) {
                    DB::table('library_sections')->where('id', $sectionId)->update(['image_path' => $image->path]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_sections');
        Schema::dropIfExists('library_plans');
        Schema::dropIfExists('library_categories');
    }
};
