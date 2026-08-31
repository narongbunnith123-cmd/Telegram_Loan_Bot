<?php

namespace App\Services\Telegram;

use App\Models\BotToken;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api as TelegramApi;

class TelegramSender
{
    public function sendToGroup(int $tenantId, string $chatId, string $message): bool
    {
        return $this->send($tenantId, $chatId, $message);
    }

    /**
     * Send a direct message to a user (Telegram uses the same API — chat_id is the user's Telegram ID).
     */
    public function sendToDM(int $tenantId, string $telegramUserId, string $message): bool
    {
        return $this->send($tenantId, $telegramUserId, $message);
    }

    /**
     * Send a message with inline keyboard buttons.
     */
    public function sendWithButtons(int $tenantId, string $chatId, string $message, array $buttons): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $keyboard = ['inline_keyboard' => [$buttons]];

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender button error for tenant {$tenantId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Answer a callback query (dismisses the "loading" state on inline buttons).
     */
    public function answerCallback(int $tenantId, string $callbackQueryId, string $text = ''): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => false,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender callback error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Edit an existing message (used to update inline button messages after action).
     */
    public function editMessage(int $tenantId, string $chatId, int $messageId, string $newText): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $newText,
                'parse_mode' => 'HTML',
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender editMessage error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a message with a persistent reply keyboard (bottom buttons).
     * Used for main menu and navigation.
     *
     * @param array $keyboard Array of rows, each row is an array of button labels
     */
    public function sendWithReplyKeyboard(int $tenantId, string $chatId, string $message, array $keyboard, bool $oneTime = false): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $replyMarkup = [
                'keyboard' => array_map(
                    fn($row) =>
                    array_map(fn($text) => ['text' => $text], $row),
                    $keyboard
                ),
                'resize_keyboard' => true,
                'one_time_keyboard' => $oneTime,
            ];

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($replyMarkup),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender reply keyboard error for tenant {$tenantId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a message with multi-row inline keyboard buttons.
     * Each element in $rows is an array of buttons: [['text' => '...', 'callback_data' => '...']]
     */
    public function sendWithInlineKeyboard(int $tenantId, string $chatId, string $message, array $rows): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $keyboard = ['inline_keyboard' => $rows];

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender inline keyboard error for tenant {$tenantId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a message and remove any reply keyboard.
     */
    public function sendAndRemoveKeyboard(int $tenantId, string $chatId, string $message): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['remove_keyboard' => true]),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender remove keyboard error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a TelegramApi instance with SSL fix for Windows.
     */
    private function makeTelegram(int $tenantId): ?TelegramApi
    {
        $botToken = BotToken::where('tenant_id', $tenantId)->first();

        if (!$botToken) {
            Log::warning("TelegramSender: No bot token for tenant {$tenantId}");
            return null;
        }

        $telegram = new TelegramApi($botToken->token);

        // Fix SSL cert issue on Windows dev environments
        $caPath = base_path('cacert.pem');
        if (file_exists($caPath)) {
            $telegram->setHttpClientHandler(
                new \Telegram\Bot\HttpClients\GuzzleHttpClient(
                    new \GuzzleHttp\Client(['verify' => $caPath])
                )
            );
        }

        return $telegram;
    }

    /**
     * Core send method.
     * Uses HTML parse mode (much more forgiving than MarkdownV2).
     */
    private function send(int $tenantId, string $chatId, string $message): bool
    {
        $telegram = $this->makeTelegram($tenantId);
        if (!$telegram)
            return false;

        try {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            Log::info("TelegramSender: Message sent to {$chatId} for tenant {$tenantId}");
            return true;
        } catch (\Exception $e) {
            Log::error("TelegramSender error for tenant {$tenantId}: " . $e->getMessage(), [
                'chat_id' => $chatId,
            ]);

            return false;
        }
    }
}
