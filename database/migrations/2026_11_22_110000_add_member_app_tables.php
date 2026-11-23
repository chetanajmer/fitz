<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('logo_path');
        });

        Schema::table('members', function (Blueprint $table) {
            // Last 10 digits of the phone number, used for OTP login lookups across gyms.
            $table->string('phone_digits', 15)->nullable()->after('phone')->index();
        });

        DB::table('members')->select(['id', 'phone'])->orderBy('id')->each(function ($row) {
            DB::table('members')->where('id', $row->id)->update([
                'phone_digits' => substr(preg_replace('/\D/', '', (string) $row->phone), -10) ?: null,
            ]);
        });

        Schema::create('member_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('phone_digits', 15)->index();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_otps');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('phone_digits'));
        Schema::table('gyms', fn (Blueprint $table) => $table->dropColumn('banner_path'));
    }
};
