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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('telegram_groups');
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->decimal('principal', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->enum('interest_type', ['fixed', 'percentage']);
            $table->decimal('interest_value', 8, 4);    // $2.00 or 2.0000 (%)
            $table->date('loan_date');
            $table->date('due_date');
            $table->enum('status', ['pending','active','overdue','paid','cancelled','blacklisted'])
                ->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
