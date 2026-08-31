<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\SendScheduledReminderJob;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\ReminderTemplate;
use App\Models\TelegramGroup;
use App\Services\Telegram\TelegramSender;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $reminders = Reminder::with(['loan.borrower', 'loan.group'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return view('reminders.index', compact('reminders'));
    }

    /**
     * Reminder settings page — templates + rules management.
     */
    public function settings()
    {
        $tenantId = auth()->user()->tenant_id;

        $allTemplates = ReminderTemplate::forTenant($tenantId)
            ->orderBy('reminder_type')
            ->orderBy('target_type')
            ->get();

        // Separate rule-based templates from daily interest templates
        $interestTypes = ['interest_normal', 'interest_warning', 'interest_escalation', 'interest_second'];
        $templates = $allTemplates->whereNotIn('reminder_type', $interestTypes)->values();
        $interestTemplates = $allTemplates->whereIn('reminder_type', $interestTypes)->values();

        $rules = ReminderRule::where('tenant_id', $tenantId)
            ->with('template')
            ->orderBy('days_offset')
            ->get();

        $placeholders = ReminderTemplate::availablePlaceholders();

        // For manual send form
        $groups = TelegramGroup::where('tenant_id', $tenantId)->get();
        $borrowers = Borrower::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('reminders.settings', compact('templates', 'interestTemplates', 'rules', 'placeholders', 'groups', 'borrowers'));
    }

    /**
     * Update a reminder template.
     */
    public function updateTemplate(Request $request, ReminderTemplate $template)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'message_template' => 'required|string|max:2000',
            'tone'             => 'required|in:gentle,balanced,aggressive',
            'enabled'          => 'boolean',
        ]);

        $validated['enabled'] = $request->has('enabled');

        // If it's a system default, clone it for this tenant
        if ($template->tenant_id === null) {
            $template = ReminderTemplate::create(array_merge($validated, [
                'tenant_id'     => auth()->user()->tenant_id,
                'reminder_type' => $template->reminder_type,
                'target_type'   => $template->target_type,
                'is_default'    => false,
            ]));
            return back()->with('success', "Custom template '{$validated['name']}' created.");
        }

        $template->update($validated);
        return back()->with('success', "Template '{$template->name}' updated.");
    }

    /**
     * Update a reminder rule.
     */
    public function updateRule(Request $request, ReminderRule $rule)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'frequency_type' => 'required|in:once,daily,every_2_days,weekly',
            'send_to_dm'     => 'boolean',
            'send_to_group'  => 'boolean',
            'send_to_admin'  => 'boolean',
            'cooldown_hours' => 'required|integer|min:1|max:720',
            'send_time'      => 'required|date_format:H:i',
            'enabled'        => 'boolean',
        ]);

        $validated['send_to_dm']    = $request->has('send_to_dm');
        $validated['send_to_group'] = $request->has('send_to_group');
        $validated['send_to_admin'] = $request->has('send_to_admin');
        $validated['enabled']       = $request->has('enabled');

        $rule->update($validated);
        return back()->with('success', "Rule '{$rule->name}' updated.");
    }

    /**
     * Preview a template rendering with sample data.
     */
    public function previewTemplate(Request $request, ReminderTemplate $template)
    {
        $loan = \App\Models\Loan::with(['borrower', 'group'])
            ->whereIn('status', ['active', 'overdue'])
            ->first();

        if (!$loan) {
            return response()->json(['preview' => '(No active loans to preview with)']);
        }

        // If custom template text is provided in the request, use that
        if ($request->has('message_template')) {
            $template = clone $template;
            $template->message_template = $request->message_template;
        }

        // For interest templates, use renderWithTracker
        if (str_starts_with($template->reminder_type, 'interest_')) {
            $tracker = \App\Models\DailyInterestTracker::where('loan_id', $loan->id)
                ->where('date', today())
                ->first();

            if ($tracker) {
                $preview = $template->renderWithTracker($loan, $tracker);
            } else {
                $preview = $template->render($loan);
            }
        } else {
            $installment = $loan->isInstallmentLoan() ? $loan->nextDueInstallment() : null;
            $preview = $template->render($loan, $installment);
        }

        return response()->json(['preview' => $preview]);
    }

    /**
     * Apply a reminder strategy preset (Gentle/Balanced/Aggressive).
     * Plan: "Reminder Strategies" section.
     */
    public function applyStrategy(Request $request)
    {
        $strategy = $request->validate(['strategy' => 'required|in:gentle,balanced,aggressive'])['strategy'];
        $tenantId = auth()->user()->tenant_id;

        // Strategy presets from Plan 1
        $presets = [
            'gentle' => [
                'before_due' => ['frequency_type' => 'once',        'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 72],
                'due_today'  => ['frequency_type' => 'once',        'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 24],
                'overdue'    => ['frequency_type' => 'every_2_days','send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 48],
                'escalation' => ['frequency_type' => 'weekly',      'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 168],
            ],
            'balanced' => [
                'before_due' => ['frequency_type' => 'once',        'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 24],
                'due_today'  => ['frequency_type' => 'once',        'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 12],
                'overdue'    => ['frequency_type' => 'daily',       'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 24],
                'escalation' => ['frequency_type' => 'weekly',      'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 168],
            ],
            'aggressive' => [
                'before_due' => ['frequency_type' => 'daily',       'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 12],
                'due_today'  => ['frequency_type' => 'once',        'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 6],
                'overdue'    => ['frequency_type' => 'daily',       'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 12],
                'escalation' => ['frequency_type' => 'daily',       'send_to_dm' => false, 'send_to_group' => true, 'send_to_admin' => false, 'cooldown_hours' => 24],
            ],
        ];

        $preset = $presets[$strategy];

        $rules = ReminderRule::where('tenant_id', $tenantId)->get();

        foreach ($rules as $rule) {
            if (isset($preset[$rule->reminder_type])) {
                $rule->update($preset[$rule->reminder_type]);
            }
        }

        return back()->with('success', "Strategy '" . ucfirst($strategy) . "' applied to all rules.");
    }

    /**
     * Send a test reminder to the Telegram group using a template.
     */
    public function testSend(Request $request, ReminderTemplate $template)
    {
        $tenantId = auth()->user()->tenant_id;

        // Find a loan to use for rendering
        $loan = \App\Models\Loan::with(['borrower', 'group', 'installments'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'overdue'])
            ->latest()
            ->first();

        if (!$loan || !$loan->group) {
            return response()->json(['success' => false, 'error' => 'No active loan with a group found.']);
        }

        // Use custom template text if provided
        $testTemplate = clone $template;
        if ($request->has('message_template')) {
            $testTemplate->message_template = $request->message_template;
        }

        // For interest templates, use renderWithTracker
        if (str_starts_with($testTemplate->reminder_type, 'interest_')) {
            $tracker = \App\Models\DailyInterestTracker::where('loan_id', $loan->id)
                ->where('date', today())
                ->first();

            if ($tracker) {
                $message = $testTemplate->renderWithTracker($loan, $tracker);
            } else {
                $message = $testTemplate->render($loan);
            }
        } else {
            $installment = $loan->isInstallmentLoan() ? $loan->nextDueInstallment() : null;
            $message = $testTemplate->render($loan, $installment);
        }

        try {
            $sender = app(\App\Services\Telegram\TelegramSender::class);
            $sender->sendToGroup(
                $tenantId,
                $loan->group->telegram_group_id,
                $message
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send a manual reminder (instant or scheduled).
     */
    public function manualSend(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'borrower_id'  => 'required|exists:borrowers,id',
            'group_id'     => 'required|exists:telegram_groups,id',
            'template_id'  => 'required|exists:reminder_templates,id',
            'send_type'    => 'required|in:now,scheduled',
            'scheduled_date' => 'required_if:send_type,scheduled|nullable|date',
            'scheduled_time' => 'required_if:send_type,scheduled|nullable|date_format:H:i',
            'mention'      => 'boolean',
        ]);

        $borrower = Borrower::findOrFail($validated['borrower_id']);
        $group = TelegramGroup::findOrFail($validated['group_id']);
        $template = ReminderTemplate::findOrFail($validated['template_id']);

        // Find an active/overdue loan for this borrower in this group
        $loan = Loan::with(['borrower', 'group', 'installments'])
            ->where('tenant_id', $tenantId)
            ->where('borrower_id', $borrower->id)
            ->where('group_id', $group->id)
            ->whereIn('status', ['active', 'overdue'])
            ->latest()
            ->first();

        if (!$loan) {
            return back()->with('error', 'No active/overdue loan found for this borrower in this group.');
        }

        // For interest templates, try to use renderWithTracker
        $isInterestTemplate = str_starts_with($template->reminder_type, 'interest_');
        if ($isInterestTemplate) {
            $tracker = \App\Models\DailyInterestTracker::where('loan_id', $loan->id)
                ->where('date', today())
                ->first();

            if ($tracker) {
                $message = $template->renderWithTracker($loan, $tracker);
            } else {
                // Create a temporary tracker-like render using standard render
                $message = $template->render($loan);
            }
        } else {
            $installment = $loan->isInstallmentLoan() ? $loan->nextDueInstallment() : null;
            $message = $template->render($loan, $installment);
        }

        // Add @mention if requested
        if ($request->has('mention') && $borrower->telegram_username) {
            $mention = "@{$borrower->telegram_username}";
            if (strpos($message, $mention) === false) {
                $message = $mention . "\n\n" . $message;
            }
        }

        $scheduledAt = null;
        if ($validated['send_type'] === 'scheduled') {
            $scheduledAt = \Carbon\Carbon::parse(
                $validated['scheduled_date'] . ' ' . $validated['scheduled_time']
            );
        }

        // Create reminder record
        $reminder = Reminder::create([
            'tenant_id'        => $tenantId,
            'loan_id'          => $loan->id,
            'borrower_id'      => $borrower->id,
            'template_id'      => $template->id,
            'type'             => $template->reminder_type,
            'target_type'      => 'group',
            'telegram_chat_id' => $group->telegram_group_id,
            'message_snapshot' => $message,
            'rendered_message' => $message,
            'scheduled_at'     => $scheduledAt ?? now(),
            'status'           => $validated['send_type'] === 'now' ? 'pending' : 'scheduled',
            'is_manual'        => true,
            'created_by'       => auth()->id(),
        ]);

        if ($validated['send_type'] === 'now') {
            // Send immediately
            try {
                $sender = app(\App\Services\Telegram\TelegramSender::class);
                if (!$sender->sendToGroup($tenantId, $group->telegram_group_id, $message)) {
                    throw new \RuntimeException('Telegram send failed. Check Laravel logs for details.');
                }

                $reminder->update(['status' => 'sent', 'sent_at' => now()]);
                return back()->with('success', "✅ Reminder sent to {$group->name}!");
            } catch (\Exception $e) {
                $reminder->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                return back()->with('error', "❌ Send failed: {$e->getMessage()}");
            }
        }

        SendScheduledReminderJob::dispatch($reminder->id)
            ->onQueue('reminders')
            ->delay($scheduledAt);

        return back()->with('success', "⏰ Reminder scheduled for {$scheduledAt->format('d M Y h:i A')}");
    }

    /**
     * Get borrowers for a specific group (AJAX).
     */
    public function borrowersForGroup(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $groupId = $request->query('group_id');

        $borrowers = Borrower::where('tenant_id', $tenantId)
            ->whereHas('loans', function ($q) use ($groupId) {
                $q->where('group_id', $groupId)
                  ->whereIn('status', ['active', 'overdue']);
            })
            ->select('id', 'name', 'telegram_username')
            ->orderBy('name')
            ->get();

        return response()->json($borrowers);
    }

    /**
     * Browser fallback for local development when schedule:work is not running.
     */
    public function processDue(TelegramSender $sender)
    {
        $tenantId = auth()->user()->tenant_id;
        $due = Reminder::where('tenant_id', $tenantId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($due as $reminder) {
            if ($reminder->scheduled_at && $reminder->scheduled_at->lt(now()->subDay())) {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => 'Scheduled reminder was missed by more than 24 hours.',
                ]);
                $failed++;
                continue;
            }

            $claimed = Reminder::whereKey($reminder->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'scheduled')
                ->update(['status' => 'pending']);

            if (!$claimed) {
                continue;
            }

            $message = $reminder->rendered_message ?? $reminder->message_snapshot;

            if (!$reminder->telegram_chat_id || !$message) {
                $reminder->update(['status' => 'failed', 'error_message' => 'Missing chat_id or message']);
                $failed++;
                continue;
            }

            if ($sender->sendToGroup($tenantId, $reminder->telegram_chat_id, $message)) {
                $reminder->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
                $sent++;
            } else {
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => 'Telegram send failed. Check Laravel logs for details.',
                ]);
                $failed++;
            }
        }

        return response()->json([
            'processed' => $due->count(),
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }
}
