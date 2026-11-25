<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The AI answer as fields (summary, changes, confidence ...); content keeps the same answer as Markdown. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_reports', fn (Blueprint $table) => $table->json('result')->nullable()->after('content'));
    }

    public function down(): void
    {
        Schema::table('ai_reports', fn (Blueprint $table) => $table->dropColumn('result'));
    }
};
