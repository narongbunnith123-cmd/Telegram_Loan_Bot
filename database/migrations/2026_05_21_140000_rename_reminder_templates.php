<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename template names to match new interest system
        DB::table('reminder_templates')
            ->where('reminder_type', 'before_due')
            ->where('target_type', 'group')
            ->update(['name' => 'Interest Reminder - Before Due (Group)']);

        DB::table('reminder_templates')
            ->where('reminder_type', 'due_today')
            ->where('target_type', 'group')
            ->update(['name' => 'Interest Due Today (Group)']);

        DB::table('reminder_templates')
            ->where('reminder_type', 'overdue')
            ->where('target_type', 'group')
            ->update(['name' => 'Interest Unpaid (Group)']);

        DB::table('reminder_templates')
            ->where('reminder_type', 'escalation')
            ->where('target_type', 'group')
            ->update(['name' => 'Interest Urgent (Group)']);
    }

    public function down(): void
    {
        DB::table('reminder_templates')
            ->where('reminder_type', 'before_due')
            ->where('target_type', 'group')
            ->update(['name' => 'Payment Due Soon (Group)']);

        DB::table('reminder_templates')
            ->where('reminder_type', 'due_today')
            ->where('target_type', 'group')
            ->update(['name' => 'Due Today (Group)']);

        DB::table('reminder_templates')
            ->where('reminder_type', 'overdue')
            ->where('target_type', 'group')
            ->update(['name' => 'Overdue Reminder (Group)']);

        DB::table('reminder_templates')
            ->where('reminder_type', 'escalation')
            ->where('target_type', 'group')
            ->update(['name' => 'Urgent Escalation (Group)']);
    }
};
