<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix reminder rules: update template references and ensure send_to_group is enabled.
 * 
 * The old rules (before_due, due_today, overdue, escalation) referenced templates 12-15
 * which are the old due-date-based templates. These rules are still valid for installment
 * loans with due dates. We ensure they have send_to_group=1 and correct template IDs.
 */
return new class extends Migration {
    public function up(): void
    {
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            // Ensure all existing rules have send_to_group enabled
            DB::table('reminder_rules')
                ->where('tenant_id', $tenantId)
                ->update(['send_to_group' => true]);

            // Map rule types to their correct template types
            $ruleTemplateMap = [
                'before_due' => 'before_due',
                'due_today' => 'due_today',
                'overdue' => 'overdue',
                'escalation' => 'escalation',
            ];

            foreach ($ruleTemplateMap as $ruleType => $templateType) {
                // Find the correct template for this tenant and type
                $template = DB::table('reminder_templates')
                    ->where('tenant_id', $tenantId)
                    ->where('reminder_type', $templateType)
                    ->where('target_type', 'group')
                    ->first();

                if ($template) {
                    DB::table('reminder_rules')
                        ->where('tenant_id', $tenantId)
                        ->where('reminder_type', $ruleType)
                        ->update(['template_id' => $template->id]);
                }
            }
        }
    }

    public function down(): void
    {
        // No rollback needed — this is a data fix
    }
};
