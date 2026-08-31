<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('installment_id')->nullable()->after('loan_id')
                  ->constrained('loan_installments')->nullOnDelete();
            $table->decimal('penalty_paid', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['installment_id']);
            $table->dropColumn(['installment_id', 'penalty_paid']);
        });
    }
};
