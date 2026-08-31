<?php

namespace App\Console\Commands;

use App\Models\BotToken;
use Illuminate\Console\Command;

class CheckWebhookInfo extends Command
{
    protected $signature = 'bot:webhook-info';
    protected $description = 'Show Telegram webhook info and pending updates';

    public function handle(): void
    {
        $botToken = BotToken::first();

        if (!$botToken) {
            $this->error('No bot token found.');
            return;
        }

        $http = new \GuzzleHttp\Client(['verify' => false]);

        // Get webhook info
        $response = $http->get("https://api.telegram.org/bot{$botToken->token}/getWebhookInfo");
        $info = json_decode($response->getBody(), true);

        $this->info('=== Webhook Info ===');
        $this->line('URL: ' . ($info['result']['url'] ?? 'Not set'));
        $this->line('Pending updates: ' . ($info['result']['pending_update_count'] ?? 0));
        $this->line('Last error: ' . ($info['result']['last_error_message'] ?? 'None'));
        $this->line('Last error date: ' . (isset($info['result']['last_error_date']) ? date('Y-m-d H:i:s', $info['result']['last_error_date']) : 'None'));
        $this->line('Allowed updates: ' . json_encode($info['result']['allowed_updates'] ?? []));
    }
}
