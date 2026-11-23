<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master photos for the Gymai default fitness library, managed by the Super Admin.
     * slot = "cat:{categoryKey}" | "plan:{planKey}:cover" | "plan:{planKey}:s{sectionIndex}".
     * Gyms receive their own copy of the file when they import.
     */
    public function up(): void
    {
        Schema::create('library_images', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 160)->unique();
            $table->string('path');
            $table->string('section_title')->nullable(); // title at upload time, for reference
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_images');
    }
};
