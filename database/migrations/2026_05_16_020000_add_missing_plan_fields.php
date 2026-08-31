<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing fields from Plan 1 to loans table
        Schema::table('loans', function (Blueprint $table) {
            $table->string('reminder_stage')->nullable()->after('reminders_enabled');    // gentle, balanced, aggressive
            $table->timestamp('next_reminder_at')->nullable()->after('last_reminder_sent_at');
        });

        // Add telegram_chat_id to reminders table (Plan 1: reminder_logs)
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('target_type');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['reminder_stage', 'next_reminder_at']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
