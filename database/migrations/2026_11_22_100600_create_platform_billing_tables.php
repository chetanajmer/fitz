<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Price history. The rate for a billing period is the latest row effective on/before the period start.
        Schema::create('pricing_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('price_per_member', 10, 2);
            $table->date('effective_from');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('effective_from');
        });

        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            // Snapshot values: never recomputed after issue, so pricing changes never alter history.
            $table->unsignedInteger('active_members');
            $table->decimal('rate_per_member', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('adjustment', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 20)->default('unpaid'); // unpaid, partial, paid, void
            $table->date('issued_on');
            $table->date('due_on');
            $table->json('meta')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'period_start']);
            $table->index('status');
        });

        Schema::create('saas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_payments');
        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('pricing_rates');
        Schema::dropIfExists('platform_settings');
    }
};
