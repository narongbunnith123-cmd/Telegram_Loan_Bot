<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->foreignId('borrower_id')->nullable()->after('loan_id')
                  ->constrained()->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->after('borrower_id')
                  ->constrained('reminder_rules')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->after('rule_id')
                  ->constrained('reminder_templates')->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->after('template_id')
                  ->constrained('loan_installments')->nullOnDelete();
            $table->enum('target_type', ['dm', 'group', 'admin'])->default('group')->after('installment_id');
            $table->text('rendered_message')->nullable()->after('message_snapshot');
            $table->string('idempotency_key')->nullable()->unique()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['borrower_id']);
            $table->dropForeign(['rule_id']);
            $table->dropForeign(['template_id']);
            $table->dropForeign(['installment_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'borrower_id', 'rule_id', 'template_id', 'installment_id',
                'target_type', 'rendered_message', 'idempotency_key',
            ]);
        });
    }
};
