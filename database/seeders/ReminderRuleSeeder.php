<?php

namespace Database\Seeders;

use App\Models\ReminderRule;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ReminderRuleSeeder extends Seeder
{
    /**
     * Seed default reminder rules for all existing tenants.
     * Uses "Balanced" strategy as default.
     */
    public function run(): void
    {
        $rules = [
            [
                'name'           => '3 Days Before Due',
                'reminder_type'  => 'before_due',
                'days_offset'    => -3,
                'frequency_type' => 'once',
                'send_to_dm'     => false,
                'send_to_group'  => true,
                'send_to_admin'  => false,
                'cooldown_hours' => 24,
            ],
            [
                'name'           => 'Due Today',
                'reminder_type'  => 'due_today',
                'days_offset'    => 0,
                'frequency_type' => 'once',
                'send_to_dm'     => false,
                'send_to_group'  => true,
                'send_to_admin'  => false,
                'cooldown_hours' => 12,
            ],
            [
                'name'           => 'Overdue 3 Days',
                'reminder_type'  => 'overdue',
                'days_offset'    => 3,
                'frequency_type' => 'daily',
                'send_to_dm'     => false,
                'send_to_group'  => true,
                'send_to_admin'  => false,
                'cooldown_hours' => 24,
            ],
            [
                'name'           => 'Overdue 7 Days',
                'reminder_type'  => 'overdue',
                'days_offset'    => 7,
                'frequency_type' => 'every_2_days',
                'send_to_dm'     => false,
                'send_to_group'  => true,
                'send_to_admin'  => false,
                'cooldown_hours' => 48,
            ],
            [
                'name'           => 'Escalation 14 Days',
                'reminder_type'  => 'escalation',
                'days_offset'    => 14,
                'frequency_type' => 'weekly',
                'send_to_dm'     => false,
                'send_to_group'  => true,
                'send_to_admin'  => false,
                'cooldown_hours' => 168,
            ],
            [
                'name'           => 'Escalation 30 Days',
                'reminder_type'  => 'escalation',
                'days_offset'    => 30,
                'frequency_type' => 'weekly',
                'send_to_dm'     => false,
                'send_to_group'  => true,
                'send_to_admin'  => false,
                'cooldown_hours' => 168,
            ],
        ];

        // Seed for each existing tenant
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            foreach ($rules as $rule) {
                ReminderRule::updateOrCreate(
                    [
                        'tenant_id'     => $tenant->id,
                        'reminder_type' => $rule['reminder_type'],
                        'days_offset'   => $rule['days_offset'],
                    ],
                    array_merge($rule, [
                        'tenant_id' => $tenant->id,
                        'enabled'   => true,
                    ])
                );
            }
        }
    }
}
