<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned member consents (ai_coach, progress_photos). One row per change, so the history is kept.
 * Members who already agreed to AI coach get a row for version 1 (the text they agreed to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // ai_coach | progress_photos
            $table->unsignedSmallInteger('version');
            $table->boolean('accepted');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type', 'id']);
        });

        DB::table('members')->whereNotNull('ai_consent_at')->select('id', 'gym_id', 'ai_consent_at')->orderBy('id')
            ->chunk(500, function ($members) {
                DB::table('member_consents')->insert($members->map(fn ($m) => [
                    'gym_id' => $m->gym_id,
                    'member_id' => $m->id,
                    'type' => 'ai_coach',
                    'version' => 1,
                    'accepted' => true,
                    'accepted_at' => $m->ai_consent_at,
                    'created_at' => $m->ai_consent_at,
                    'updated_at' => $m->ai_consent_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_consents');
    }
};
