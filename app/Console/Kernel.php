<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
        protected function schedule(Schedule $schedule): void
        {
                // ── Every Minute ─────────────────────────────
                // Process scheduled (manual) reminders that are due
                $schedule->command('reminders:process-scheduled')
                        ->everyMinute();

                // ── Every 5 Minutes ──────────────────────────
                // Process auto reminders (rules + templates + send_time check)
                $schedule->command('loans:send-reminders')
                        ->everyFiveMinutes();

                // ── Daily at Midnight ────────────────────────
                // Mark overdue loans
                $schedule->command('loans:check-overdue')
                        ->dailyAt('00:05');

                // Calculate daily interest
                $schedule->command('loans:calculate-interest')
                        ->dailyAt('00:10');

                // Apply penalties to overdue installments
                $schedule->command('loans:process-overdue')
                        ->dailyAt('00:15');

                // Create daily interest tracker records (with auto-backfill for missed days)
                $schedule->command('reminders:daily-interest --create-records')
                        ->dailyAt('00:20');

                // ── Every Minute ─────────────────────────────
                // Send interest reminders (checks configurable times internally)
                // Also creates records if missing (self-healing after schedule:work restart)
                $schedule->command('reminders:daily-interest --create-records --send-reminders')
                        ->everyMinute();

                // ── Every 10 Minutes ────────────────────────
                // Clean up expired Telegram conversation sessions
                $schedule->call(function () {
                        \App\Services\Telegram\ConversationManager::cleanExpiredSessions();
                })->everyTenMinutes();

                // ── Every Minute ─────────────────────────────
                // Auto-expire pending payment sessions past their expiry time
                $schedule->command('payment:expire-sessions')
                        ->everyMinute();
        }

        protected function commands(): void
        {
                $this->load(__DIR__ . '/Commands');
                require base_path('routes/console.php');
        }
}
