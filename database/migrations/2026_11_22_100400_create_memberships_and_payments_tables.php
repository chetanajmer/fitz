<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('renewed_from_id')->nullable()->constrained('memberships')->nullOnDelete();
            // Snapshot of the plan at purchase time so later plan edits never rewrite history.
            $table->string('plan_name');
            $table->string('duration_type', 20);
            $table->unsignedInteger('duration_days');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('status', 20)->default('active'); // upcoming, active, expired, cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'end_date']);
            $table->index(['member_id', 'end_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('receipt_number', 40);
            $table->decimal('amount', 10, 2);
            $table->string('method', 20); // cash, upi, bank_transfer, card
            $table->string('reference')->nullable(); // UPI ref / txn id
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            // Reserved for future online payment gateway integrations.
            $table->string('status', 20)->default('completed'); // completed, pending, failed, refunded
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gym_id', 'sequence']);
            $table->unique(['gym_id', 'receipt_number']);
            $table->index(['gym_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('memberships');
    }
};
