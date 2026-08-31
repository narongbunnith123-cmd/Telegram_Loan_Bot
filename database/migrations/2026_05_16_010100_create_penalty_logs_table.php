<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('loan_installments')->cascadeOnDelete();
            $table->date('penalty_date');
            $table->decimal('penalty_amount', 10, 2);
            $table->unsignedInteger('days_late');
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->timestamp('created_at')->useCurrent();

            // Prevent double-charging: one penalty per installment per day
            $table->unique(['installment_id', 'penalty_date']);
            $table->index(['loan_id', 'penalty_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_logs');
    }
};
