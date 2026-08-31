<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update "Payment Due Soon" templates
        DB::table('reminder_templates')
            ->where('reminder_type', 'before_due')
            ->where('target_type', 'group')
            ->update(['message_template' =>
                "📅 <b>Payment Reminder</b>\n"
                . "\n"
                . "Hi {borrower_username},\n"
                . "\n"
                . "Just a friendly reminder that your payment is due soon.\n"
                . "\n"
                . "💰 Borrowed: {loan_amount}\n"
                . "📊 Remaining Principal: <b>{remaining_principal}</b>\n"
                . "📈 Daily Interest: {daily_interest}\n"
                . "⚠️ Unpaid Interest: {unpaid_interest}\n"
                . "💵 Total Due: <b>{outstanding_balance}</b>\n"
                . "📆 Due Date: <b>{due_date}</b>\n"
                . "\n"
                . "Please arrange payment before the due date.\n"
                . "Use /pay to submit payment proof. 🙏",
            ]);

        // Update "Due Today" templates
        DB::table('reminder_templates')
            ->where('reminder_type', 'due_today')
            ->where('target_type', 'group')
            ->update(['message_template' =>
                "🔔 <b>Payment Due Today!</b>\n"
                . "\n"
                . "{borrower_username}\n"
                . "\n"
                . "Your payment is due <b>TODAY</b>.\n"
                . "\n"
                . "💰 Borrowed: {loan_amount}\n"
                . "📊 Remaining Principal: <b>{remaining_principal}</b>\n"
                . "📈 Daily Interest: {daily_interest}\n"
                . "⚠️ Unpaid Interest: {unpaid_interest}\n"
                . "💵 Total Due: <b>{outstanding_balance}</b>\n"
                . "\n"
                . "💳 Please make your payment today to stay on track.\n"
                . "Use /pay to submit payment proof.",
            ]);

        // Update "Overdue Reminder" templates
        DB::table('reminder_templates')
            ->where('reminder_type', 'overdue')
            ->where('target_type', 'group')
            ->update([
                'message_template' =>
                    "⚠️ <b>Interest Payment Unpaid — Day {days_overdue}</b>\n"
                    . "\n"
                    . "{borrower_username}\n"
                    . "\n"
                    . "Your interest has been unpaid for <b>{days_overdue} days</b>.\n"
                    . "\n"
                    . "💰 Borrowed: {loan_amount}\n"
                    . "📊 Remaining Principal: <b>{remaining_principal}</b>\n"
                    . "📈 Daily Interest: {daily_interest}\n"
                    . "🔴 Total Unpaid: <b>{outstanding_balance}</b>\n"
                    . "📆 Due Date: {due_date}\n"
                    . "\n"
                    . "Please settle your outstanding interest immediately.\n"
                    . "Use /pay to submit payment proof.",
            ]);

        // Update "Urgent Escalation" templates
        DB::table('reminder_templates')
            ->where('reminder_type', 'escalation')
            ->where('target_type', 'group')
            ->update([
                'message_template' =>
                    "🚨 <b>URGENT — Interest Severely Unpaid</b>\n"
                    . "\n"
                    . "{borrower_username}\n"
                    . "\n"
                    . "⚠️ Your interest has been unpaid for <b>{days_overdue} consecutive days</b>.\n"
                    . "\n"
                    . "🔴 Total Unpaid: <b>{outstanding_balance}</b>\n"
                    . "📊 Remaining Principal: {remaining_principal}\n"
                    . "💰 Original Loan: {loan_amount}\n"
                    . "📆 Due Date: {due_date}\n"
                    . "\n"
                    . "⚠️ Continued non-payment may result in further action.\n"
                    . "Please contact us immediately to resolve this.",
            ]);
    }

    public function down(): void
    {
        // Revert not needed — templates can be manually edited
    }
};
