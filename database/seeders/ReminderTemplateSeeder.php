<?php

namespace Database\Seeders;

use App\Models\ReminderTemplate;
use Illuminate\Database\Seeder;

class ReminderTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ── Gentle DM ──────────────────────────
            [
                'name'             => 'Payment Due Soon (DM)',
                'reminder_type'    => 'before_due',
                'target_type'      => 'dm',
                'tone'             => 'gentle',
                'message_template' => "📋 Payment Reminder\n\nHi {borrower_name},\n\nJust a friendly reminder that your payment of {outstanding_balance} is due on {due_date}.\n\nPlease arrange payment at your convenience.\n\nThank you! 🙏",
                'is_default'       => true,
            ],

            // ── Due Today (Group) ──────────────────
            [
                'name'             => 'Due Today (Group)',
                'reminder_type'    => 'due_today',
                'target_type'      => 'group',
                'tone'             => 'balanced',
                'message_template' => "⏰ Payment Due Today\n\nBorrower: {borrower_username}\nAmount: {outstanding_balance}\nDue: Today\n\nPlease make your payment today.",
                'is_default'       => true,
            ],

            // ── Overdue DM ─────────────────────────
            [
                'name'             => 'Overdue Reminder (DM)',
                'reminder_type'    => 'overdue',
                'target_type'      => 'dm',
                'tone'             => 'balanced',
                'message_template' => "⚠️ Overdue Notice\n\nHi {borrower_name},\n\nYour payment is overdue by {days_overdue} days.\n\n💰 Outstanding: {outstanding_balance}\n📅 Due Date: {due_date}\n📈 Daily Interest: {daily_interest}\n💸 Penalty: {penalty_amount}\n\nPlease contact the admin to settle your payment.",
                'is_default'       => true,
            ],

            // ── Overdue Group ──────────────────────
            [
                'name'             => 'Overdue Alert (Group)',
                'reminder_type'    => 'overdue',
                'target_type'      => 'group',
                'tone'             => 'balanced',
                'message_template' => "⚠️ Overdue Alert\n\nBorrower: {borrower_username}\nOutstanding: {outstanding_balance}\nOverdue: {days_overdue} days\nPenalty: {penalty_amount}",
                'is_default'       => true,
            ],

            // ── Escalation (DM) ────────────────────
            [
                'name'             => 'Escalation Warning (DM)',
                'reminder_type'    => 'escalation',
                'target_type'      => 'dm',
                'tone'             => 'aggressive',
                'message_template' => "🚨 URGENT: Payment Severely Overdue\n\n{borrower_name}, your payment has been overdue for {days_overdue} days.\n\n💰 Total Due: {outstanding_balance}\n💸 Accumulated Penalty: {penalty_amount}\n\n⚠️ Failure to respond may result in further action.\n\nPlease contact admin immediately.",
                'is_default'       => true,
            ],

            // ── Admin Alert ────────────────────────
            [
                'name'             => 'Admin Alert',
                'reminder_type'    => 'admin_alert',
                'target_type'      => 'admin',
                'tone'             => 'aggressive',
                'message_template' => "🔔 Admin Alert\n\nBorrower {borrower_name} has ignored reminders for {days_overdue} days.\n\nOutstanding: {outstanding_balance}\nPenalty: {penalty_amount}\nGroup: {group_name}\n\nConsider taking action.",
                'is_default'       => true,
            ],
        ];

        foreach ($templates as $template) {
            ReminderTemplate::updateOrCreate(
                [
                    'tenant_id'     => null,
                    'reminder_type' => $template['reminder_type'],
                    'target_type'   => $template['target_type'],
                    'is_default'    => true,
                ],
                array_merge($template, [
                    'tenant_id' => null, // System-level defaults
                    'enabled'   => true,
                ])
            );
        }
    }
}
