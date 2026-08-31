<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrower_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('bank, cash, wallet, other');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('label')->nullable()->comment('User-friendly label, e.g. "ABA Main"');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['borrower_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_payment_methods');
    }
};
