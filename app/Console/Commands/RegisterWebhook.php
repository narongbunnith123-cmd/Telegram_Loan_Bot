<?php

namespace App\Console\Commands;

use App\Models\BotToken;
use Illuminate\Console\Command;
use Telegram\Bot\Api as TelegramApi;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class RegisterWebhook extends Command
{
    protected $signature = 'bot:register-webhook';
    protected $description = 'Register the Telegram webhook with the current APP_URL';

    public function handle(): void
    {
        $botToken = BotToken::first();

        if (!$botToken) {
            $this->error('No bot token found. Connect your bot first via the web dashboard.');
            return;
        }

        // Recalculate webhook URL using current APP_URL
        $webhookUrl = url("/api/telegram/webhook/{$botToken->webhook_token}");

        $this->info("Bot: @{$botToken->bot_username}");
        $this->info("Old URL: {$botToken->webhook_url}");
        $this->info("New URL: {$webhookUrl}");

        // Update in database
        $botToken->update(['webhook_url' => $webhookUrl]);

        // Register with Telegram
        if (str_starts_with($webhookUrl, 'https://')) {
            $caPath = base_path('cacert.pem');
            $verify = file_exists($caPath) ? $caPath : false;
            $httpClient = new GuzzleHttpClient(new \GuzzleHttp\Client(['verify' => $verify]));
            $telegram = new TelegramApi($botToken->token, false, $httpClient);

            $telegram->setWebhook([
                'url'             => $webhookUrl,
                'secret_token'    => $botToken->webhook_token,
                'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
            ]);

            $botToken->update(['webhook_set' => true]);
            $this->info('✅ Webhook registered with Telegram!');

            // Verify
            $info = $telegram->getWebhookInfo();
            $this->info("Telegram confirms: {$info->getUrl()}");
        } else {
            $this->warn('⚠️ URL is not HTTPS — webhook NOT registered with Telegram.');
            $this->warn('Set APP_URL to your ngrok HTTPS URL and try again.');
        }
    }
}
