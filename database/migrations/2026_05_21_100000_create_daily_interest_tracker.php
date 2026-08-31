<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_interest_tracker', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('borrower_id')->index();
            $table->date('date')->index();
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('cumulative_unpaid', 15, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->boolean('reminder_1_sent')->default(false);
            $table->timestamp('reminder_1_sent_at')->nullable();
            $table->boolean('reminder_2_sent')->default(false);
            $table->timestamp('reminder_2_sent_at')->nullable();
            $table->unsignedInteger('consecutive_unpaid_days')->default(0);
            $table->string('stage', 20)->default('normal'); // normal, warning, escalation
            $table->timestamps();

            $table->unique(['loan_id', 'date']); // One record per loan per day
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('borrower_id')->references('id')->on('borrowers')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });

        // Add configurable reminder times to telegram_groups
        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->string('reminder_time_1', 5)->default('17:00')->after('settings');
            $table->string('reminder_time_2', 5)->default('21:00')->after('reminder_time_1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_interest_tracker');

        Schema::table('telegram_groups', function (Blueprint $table) {
            $table->dropColumn(['reminder_time_1', 'reminder_time_2']);
        });
    }
};
