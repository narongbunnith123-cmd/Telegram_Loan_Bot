<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Expand the reminder_type ENUM to include new interest types
        DB::statement("ALTER TABLE `reminder_templates` MODIFY `reminder_type` ENUM('before_due','due_today','overdue','escalation','interest_normal','interest_warning','interest_escalation','interest_second') NOT NULL");

        // Step 2: Insert templates for each tenant
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            // Skip if already exists
            if (DB::table('reminder_templates')->where('tenant_id', $tenantId)->where('reminder_type', 'interest_normal')->exists()) {
                continue;
            }

            DB::table('reminder_templates')->insert([
                [
                    'tenant_id'        => $tenantId,
                    'name'             => '📌 Daily Interest - Normal (1st)',
                    'reminder_type'    => 'interest_normal',
                    'target_type'      => 'group',
                    'tone'             => 'gentle',
                    'message_template' => "📌 <b>Daily Interest Reminder</b>\n\n{borrower_username}\n\n💰 Borrowed: {loan_amount}\n📅 Today's Interest: {today_interest}\n⚠️ Unpaid Interest: {unpaid_interest}\n💵 Total Due: {total_due}\n📊 Remaining Principal: {remaining_principal}\n📆 Due Date: {due_date}\n\nPlease pay today's interest before tonight.",
                    'enabled'          => true,
                    'is_default'       => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
                [
                    'tenant_id'        => $tenantId,
                    'name'             => '⚠️ Daily Interest - Warning (1st)',
                    'reminder_type'    => 'interest_warning',
                    'target_type'      => 'group',
                    'tone'             => 'balanced',
                    'message_template' => "⚠️ <b>Interest Payment Unpaid — Day {unpaid_days}</b>\n\n{borrower_username}\n\nYour interest has been unpaid for {unpaid_days} days.\n\n💰 Borrowed: {loan_amount}\n📅 Today's Interest: {today_interest}\n🔴 Total Unpaid: {total_due}\n📊 Remaining Principal: {remaining_principal}\n📆 Due Date: {due_date}\n\nPlease settle your outstanding interest immediately.",
                    'enabled'          => true,
                    'is_default'       => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
                [
                    'tenant_id'        => $tenantId,
                    'name'             => '🚨 Daily Interest - Escalation (1st)',
                    'reminder_type'    => 'interest_escalation',
                    'target_type'      => 'group',
                    'tone'             => 'aggressive',
                    'message_template' => "🚨 <b>URGENT — Interest Severely Unpaid</b>\n\n{borrower_username}\n\n⚠️ Your interest has been unpaid for {unpaid_days} consecutive days.\n\n🔴 Total Unpaid Interest: {total_due}\n📊 Remaining Principal: {remaining_principal}\n💰 Original Loan: {loan_amount}\n📆 Due Date: {due_date}\n\nImmediate payment is required. Continued non-payment may result in additional actions.",
                    'enabled'          => true,
                    'is_default'       => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
                [
                    'tenant_id'        => $tenantId,
                    'name'             => '⚠️ Daily Interest - 2nd Reminder',
                    'reminder_type'    => 'interest_second',
                    'target_type'      => 'group',
                    'tone'             => 'balanced',
                    'message_template' => "⚠️ <b>Second Reminder</b>\n\n{borrower_username}\n\nYour interest payment is still unpaid today.\n\n🔴 Total Due: {total_due}\n\nPlease complete payment to avoid additional accumulation tomorrow.",
                    'enabled'          => true,
                    'is_default'       => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('reminder_templates')
            ->whereIn('reminder_type', ['interest_normal', 'interest_warning', 'interest_escalation', 'interest_second'])
            ->delete();

        DB::statement("ALTER TABLE `reminder_templates` MODIFY `reminder_type` ENUM('before_due','due_today','overdue','escalation') NOT NULL");
    }
};
