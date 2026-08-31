<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('source'); // Gateway name: simulated, aba, khqr, stripe, paypal
            $table->string('transaction_id')->index(); // Gateway's transaction ID (idempotency key)
            $table->json('payload'); // Complete webhook payload
            $table->text('signature')->nullable(); // Webhook signature for validation
            
            $table->enum('status', ['received', 'processing', 'processed', 'failed', 'duplicate'])
                ->default('received')
                ->index();
            
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Enforce idempotency: one transaction_id per tenant (Requirement 13.6)
            $table->unique(['tenant_id', 'transaction_id'], 'unique_tenant_transaction');
            
            // Index for admin queries (Requirement 13.7)
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
