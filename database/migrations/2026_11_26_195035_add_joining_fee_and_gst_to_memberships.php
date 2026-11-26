<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-time joining fee and GST on member fees. The gym's settings (Gym Profile, Fees & GST) are copied onto each
 * membership when it is created, so later changes never alter old invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->decimal('joining_fee', 10, 2)->default(0)->after('discount');
            $table->decimal('gst_rate', 5, 2)->nullable()->after('total_amount'); // null = no GST; prices include GST
            $table->string('gstin', 15)->nullable()->after('gst_rate');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn(['joining_fee', 'gst_rate', 'gstin']));
    }
};
