<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->date('due_date');
            $table->decimal('base_amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);  // base + penalty - paid
            $table->unsignedInteger('late_days')->default(0);
            $table->enum('status', ['pending', 'partial', 'overdue', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['loan_id', 'due_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'due_date']); // for overdue queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
