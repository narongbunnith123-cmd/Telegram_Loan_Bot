<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('loan_type', ['lump_sum', 'installment'])->default('lump_sum')->after('notes');
            $table->unsignedInteger('duration_months')->nullable()->after('loan_type');
            $table->decimal('monthly_installment', 12, 2)->nullable()->after('duration_months');
            $table->enum('penalty_type', ['none', 'fixed', 'percentage'])->default('none')->after('monthly_installment');
            $table->decimal('penalty_value', 8, 4)->nullable()->after('penalty_type');
            $table->unsignedTinyInteger('grace_days')->default(3)->after('penalty_value');
            $table->decimal('max_penalty_percent', 5, 2)->nullable()->after('grace_days');
            $table->boolean('reminders_enabled')->default(true)->after('max_penalty_percent');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'loan_type', 'duration_months', 'monthly_installment',
                'penalty_type', 'penalty_value', 'grace_days',
                'max_penalty_percent', 'reminders_enabled', 'last_reminder_sent_at',
            ]);
        });
    }
};
