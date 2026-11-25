<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time sign-in codes for the website, sent by SMS or email to a member or a gym staff account.
 * Only a hash of the code is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10); // sms | email
            $table->string('destination'); // 10-digit mobile or lower-case email
            $table->string('account', 30); // staff:{user id} | member:{member id}
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['destination', 'account']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
