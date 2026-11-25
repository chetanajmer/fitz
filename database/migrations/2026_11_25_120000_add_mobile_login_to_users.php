<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign in with a mobile number:
 *  - users.login_phone: a staff member's verified mobile (10 digits), unique, used to sign in (with a password or a one-time code);
 *  - users.email becomes optional: members who sign in with an OTP may have no email;
 *  - phone_verifications.purpose: the same OTP table serves app sign-up, staff login and staff phone checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('login_phone', 10)->nullable()->unique()->after('phone');
        });

        Schema::table('phone_verifications', function (Blueprint $table) {
            $table->string('purpose', 20)->default('signup')->after('phone_digits');
        });
    }

    public function down(): void
    {
        Schema::table('phone_verifications', fn (Blueprint $table) => $table->dropColumn('purpose'));

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_phone']);
            $table->dropColumn('login_phone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
