<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference_code')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('gateway_name');

            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled', 'failed'])
                ->default('pending')
                ->index();

            $table->text('qr_payload')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Indexes for fast lookups
            $table->index(['tenant_id', 'status']);
            $table->index(['loan_id', 'status']);
            $table->index(['gateway_name', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
    }
};
