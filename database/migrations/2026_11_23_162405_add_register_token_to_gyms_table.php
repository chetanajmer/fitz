<?php

use App\Models\Gym;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member self-registration gets its own secret token, separate from the public
 * enquiry join_token, so a shared join link can no longer create members.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->string('register_token', 64)->nullable()->unique()->after('join_token');
        });

        DB::table('gyms')->whereNull('register_token')->orderBy('id')->pluck('id')->each(
            fn (int $id) => DB::table('gyms')->where('id', $id)->update(['register_token' => Gym::generateRegisterToken()])
        );
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropUnique(['register_token']);
            $table->dropColumn('register_token');
        });
    }
};
