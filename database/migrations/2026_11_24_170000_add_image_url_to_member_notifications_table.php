<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The picture of a notification as its own column (full public URL), so the app's notification
 * list and detail page can show it without digging into the data payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_notifications', function (Blueprint $table) {
            $table->string('image_url', 500)->nullable()->after('body');
        });

        // Notifications already sent with a picture kept it in data.image_url.
        DB::table('member_notifications')->whereNotNull('data')->orderBy('id')->each(function ($row) {
            $url = json_decode((string) $row->data, true)['image_url'] ?? null;
            if (is_string($url) && $url !== '') {
                DB::table('member_notifications')->where('id', $row->id)->update(['image_url' => mb_substr($url, 0, 500)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_notifications', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
