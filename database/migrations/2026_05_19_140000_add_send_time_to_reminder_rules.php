<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_rules', function (Blueprint $table) {
            $table->string('send_time', 5)->default('08:00')->after('cooldown_hours');
        });

        // Set sensible defaults for existing rules
        \App\Models\ReminderRule::where('reminder_type', 'before_due')->update(['send_time' => '09:00']);
        \App\Models\ReminderRule::where('reminder_type', 'due_today')->update(['send_time' => '08:00']);
        \App\Models\ReminderRule::where('reminder_type', 'overdue')->update(['send_time' => '08:00']);
        \App\Models\ReminderRule::where('reminder_type', 'escalation')->update(['send_time' => '09:00']);
    }

    public function down(): void
    {
        Schema::table('reminder_rules', function (Blueprint $table) {
            $table->dropColumn('send_time');
        });
    }
};
