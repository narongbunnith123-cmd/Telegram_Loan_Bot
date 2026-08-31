<?php

namespace App\Services\Telegram\Commands;

use App\Models\Tenant;
use App\Models\TelegramGroup;
use App\Services\Telegram\AdminGuard;
use App\Services\Telegram\TelegramSender;

class SettingsCommand
{
    public function __construct(
        private TelegramSender $sender,
        private AdminGuard $guard,
    ) {}

    /**
     * Usage:
     *   /settings                         — view current settings
     *   /settings reminder daily 14:00    — set frequency + time
     *   /settings reminder off            — disable reminders
     *   /settings currency ៛              — change currency
     *   /settings interest 2.5 percentage — set interest
     *   /settings penalty 1.00            — late penalty
     *   /settings warn 3                  — days before due to warn
     */
    public function handle(Tenant $tenant, array $message): void
    {
        $userId = (string) data_get($message, 'from.id');
        $chatId = (string) data_get($message, 'chat.id');

        if (!$this->guard->isAdmin($tenant, $userId)) {
            $this->sender->sendToGroup($tenant->id, $chatId, "⛔ You are not authorized.");
            return;
        }

        $group = TelegramGroup::where('tenant_id', $tenant->id)
            ->where('telegram_group_id', $chatId)
            ->first();

        if (!$group) {
            $this->sender->sendToGroup($tenant->id, $chatId, "❌ Group not registered.");
            return;
        }

        $text  = data_get($message, 'text', '');
        $parts = preg_split('/\s+/', trim($text));
        // parts[0] = /settings, [1] = key, [2..] = values

        $key = strtolower($parts[1] ?? '');

        // No arguments — show current settings
        if (empty($key)) {
            $this->showSettings($tenant, $chatId, $group);
            return;
        }

        $settings = is_array($group->settings) ? $group->settings : [];

        switch ($key) {
            case 'reminder':
                $this->updateReminder($tenant, $chatId, $group, $settings, $parts);
                break;
            case 'currency':
                $this->updateSimple($tenant, $chatId, $group, $settings, 'currency', $parts[2] ?? null, '💱');
                break;
            case 'interest':
                $this->updateInterest($tenant, $chatId, $group, $settings, $parts);
                break;
            case 'penalty':
                $this->updateSimple($tenant, $chatId, $group, $settings, 'late_penalty', $parts[2] ?? null, '💸');
                break;
            case 'warn':
                $this->updateSimple($tenant, $chatId, $group, $settings, 'reminder_days_before', $parts[2] ?? null, '📅');
                break;
            default:
                $this->sender->sendToGroup($tenant->id, $chatId,
                    "❌ Unknown setting: <code>{$this->esc($key)}</code>\n\n"
                    . "Available: <code>reminder</code>, <code>currency</code>, <code>interest</code>, <code>penalty</code>, <code>warn</code>");
                break;
        }
    }

    private function showSettings(Tenant $tenant, string $chatId, TelegramGroup $group): void
    {
        $s = is_array($group->settings) ? $group->settings : [];

        $currency  = $s['currency'] ?? '$';
        $intRate   = $s['interest_rate'] ?? '—';
        $intType   = $s['interest_type'] ?? 'percentage';
        $intLabel  = $intType === 'fixed' ? "{$currency}{$intRate}/day" : "{$intRate}%/day";
        $freq      = $s['reminder_frequency'] ?? 'daily';
        $time      = $s['reminder_time'] ?? '09:00';
        $daysBefore = $s['reminder_days_before'] ?? 3;
        $penalty   = $s['late_penalty'] ?? '0';

        $freqLabel = match ($freq) {
            'off'         => '🔇 Off',
            'every_6h'    => '🔄 Every 6 hours',
            'every_12h'   => '🔄 Every 12 hours',
            'twice_daily' => '🔔 Twice daily',
            'daily'       => '🔔 Daily',
            'weekly'      => '📅 Weekly (Monday)',
            default       => $freq,
        };

        $msg = "⚙️ <b>Group Settings — {$this->esc($group->name)}</b>\n\n"
            . "💱 Currency: <code>{$this->esc($currency)}</code>\n"
            . "📈 Interest: <code>{$this->esc($intLabel)}</code>\n"
            . "⏰ Reminder: {$freqLabel}";

        if ($freq !== 'off' && !in_array($freq, ['every_6h', 'every_12h'])) {
            $msg .= " at <code>{$time}</code>";
        }

        $msg .= "\n📅 Warn Before Due: <code>{$daysBefore} days</code>\n"
            . "💸 Late Penalty: <code>{$currency}{$penalty}/day</code>\n\n"
            . "━━━━━━━━━━━━━━━━━━━━━━\n"
            . "📝 <b>Edit commands:</b>\n"
            . "<code>/settings reminder daily 14:00</code>\n"
            . "<code>/settings reminder off</code>\n"
            . "<code>/settings currency \\$</code>\n"
            . "<code>/settings interest 2.5 percentage</code>\n"
            . "<code>/settings penalty 1.00</code>\n"
            . "<code>/settings warn 3</code>";

        $this->sender->sendToGroup($tenant->id, $chatId, $msg);
    }

    private function updateReminder(Tenant $tenant, string $chatId, TelegramGroup $group, array $settings, array $parts): void
    {
        $freq = strtolower($parts[2] ?? '');
        $time = $parts[3] ?? null;

        $validFreqs = ['off', 'every_6h', 'every_12h', 'twice_daily', 'daily', 'weekly'];

        if (!in_array($freq, $validFreqs)) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "❌ Invalid frequency: <code>{$this->esc($freq)}</code>\n\n"
                . "Options: <code>off</code>, <code>every_6h</code>, <code>every_12h</code>, <code>twice_daily</code>, <code>daily</code>, <code>weekly</code>\n\n"
                . "Example: <code>/settings reminder daily 14:00</code>");
            return;
        }

        $settings['reminder_frequency'] = $freq;

        // Validate and set time if provided
        if ($time && preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $hour = (int) explode(':', $time)[0];
            $min  = (int) explode(':', $time)[1];
            if ($hour >= 0 && $hour <= 23 && $min >= 0 && $min <= 59) {
                $settings['reminder_time'] = sprintf('%02d:%02d', $hour, $min);
            }
        }

        $group->update(['settings' => $settings]);

        $freqLabel = match ($freq) {
            'off'         => '🔇 Reminders disabled',
            'every_6h'    => '🔄 Every 6 hours',
            'every_12h'   => '🔄 Every 12 hours',
            'twice_daily' => "🔔 Twice daily at {$settings['reminder_time']} \\& " . sprintf('%02d:%02d', ((int)explode(':', $settings['reminder_time'])[0] + 12) % 24, (int)explode(':', $settings['reminder_time'])[1]),
            'daily'       => "🔔 Daily at {$settings['reminder_time']}",
            'weekly'      => "📅 Weekly (Monday) at {$settings['reminder_time']}",
            default       => $freq,
        };

        $this->sender->sendToGroup($tenant->id, $chatId,
            "✅ <b>Reminder Updated</b>\n\n⏰ {$freqLabel}");
    }

    private function updateInterest(Tenant $tenant, string $chatId, TelegramGroup $group, array $settings, array $parts): void
    {
        $rate = $parts[2] ?? null;
        $type = strtolower($parts[3] ?? 'percentage');

        if (!$rate || !is_numeric($rate)) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "❌ Usage: <code>/settings interest RATE TYPE</code>\n\n"
                . "Example: <code>/settings interest 2.5 percentage</code>\n"
                . "Example: <code>/settings interest 5 fixed</code>");
            return;
        }

        if (!in_array($type, ['percentage', 'fixed'])) {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "❌ Type must be <code>percentage</code> or <code>fixed</code>");
            return;
        }

        $settings['interest_rate'] = (float) $rate;
        $settings['interest_type'] = $type;
        $group->update(['settings' => $settings]);

        $currency = $settings['currency'] ?? '$';
        $label = $type === 'fixed' ? "{$currency}{$rate}/day" : "{$rate}%/day";

        $this->sender->sendToGroup($tenant->id, $chatId,
            "✅ <b>Interest Updated</b>\n\n📈 Default interest: <code>{$this->esc($label)}</code>");
    }

    private function updateSimple(Tenant $tenant, string $chatId, TelegramGroup $group, array $settings, string $key, ?string $value, string $emoji): void
    {
        if ($value === null || $value === '') {
            $this->sender->sendToGroup($tenant->id, $chatId,
                "❌ Usage: <code>/settings {$key} VALUE</code>");
            return;
        }

        $settings[$key] = is_numeric($value) ? (float) $value : $value;
        $group->update(['settings' => $settings]);

        $display = match ($key) {
            'currency'             => "Currency: <code>{$this->esc($value)}</code>",
            'late_penalty'         => "Late Penalty: <code>{$value}/day</code>",
            'reminder_days_before' => "Warn: <code>{$value} days</code> before due",
            default                => "<code>{$key}</code> = <code>{$value}</code>",
        };

        $this->sender->sendToGroup($tenant->id, $chatId,
            "✅ <b>Setting Updated</b>\n\n{$emoji} {$display}");
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
