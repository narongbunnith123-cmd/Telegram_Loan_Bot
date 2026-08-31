<?php

namespace Database\Seeders;

use App\Models\ReminderTemplate;
use App\Models\ReminderRule;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ReminderGroupTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Step 1: Delete ALL non-group templates and duplicates
            ReminderTemplate::where('tenant_id', $tenant->id)
                ->where('target_type', '!=', 'group')
                ->delete();

            // Delete system defaults too
            ReminderTemplate::whereNull('tenant_id')
                ->where('target_type', '!=', 'group')
                ->delete();

            // Delete ALL existing group templates to start fresh (no duplicates)
            ReminderTemplate::where('tenant_id', $tenant->id)
                ->where('target_type', 'group')
                ->delete();
        }

        // Step 2: Create exactly 4 GROUP templates
        $templates = [
            [
                'name'            => 'Interest Reminder - Before Due (Group)',
                'reminder_type'   => 'before_due',
                'target_type'     => 'group',
                'tone'            => 'gentle',
                'message_template' =>
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
            ],
            [
                'name'            => 'Interest Due Today (Group)',
                'reminder_type'   => 'due_today',
                'target_type'     => 'group',
                'tone'            => 'balanced',
                'message_template' =>
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
            ],
            [
                'name'            => 'Interest Unpaid (Group)',
                'reminder_type'   => 'overdue',
                'target_type'     => 'group',
                'tone'            => 'balanced',
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
            ],
            [
                'name'            => 'Interest Urgent (Group)',
                'reminder_type'   => 'escalation',
                'target_type'     => 'group',
                'tone'            => 'aggressive',
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
            ],
        ];

        // Rule type → template type mapping
        $ruleTypeToTemplate = [
            'before_due' => 'before_due',
            'due_today'  => 'due_today',
            'overdue'    => 'overdue',
            'escalation' => 'escalation',
        ];

        foreach ($tenants as $tenant) {
            // Create templates
            $templateMap = [];
            foreach ($templates as $tmpl) {
                $created = ReminderTemplate::create(array_merge($tmpl, [
                    'tenant_id'  => $tenant->id,
                    'enabled'    => true,
                    'is_default' => false,
                ]));
                $templateMap[$tmpl['reminder_type']] = $created->id;
            }

            // Step 3: Update rules → GROUP-only + link to templates
            $rules = ReminderRule::where('tenant_id', $tenant->id)->get();
            foreach ($rules as $rule) {
                $templateId = $templateMap[$ruleTypeToTemplate[$rule->reminder_type] ?? ''] ?? null;
                $rule->update([
                    'send_to_dm'    => false,
                    'send_to_group' => true,
                    'send_to_admin' => false,
                    'template_id'   => $templateId,
                ]);
            }
        }
    }
}
