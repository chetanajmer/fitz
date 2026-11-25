<?php

use App\Models\Gym;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Secret behind the QR code members scan at their gym to check in and out (see CheckInCodeService). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('checkin_token', 64)->nullable()->unique()->after('register_token');
        });

        DB::table('gyms')->whereNull('checkin_token')->orderBy('id')->pluck('id')->each(
            fn (int $id) => DB::table('gyms')->where('id', $id)->update(['checkin_token' => Gym::generateCheckinToken()])
        );
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropUnique(['checkin_token']);
            $table->dropColumn('checkin_token');
        });
    }
};
