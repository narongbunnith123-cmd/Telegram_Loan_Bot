<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BotToken;
use App\Services\Telegram\AdminGuard;
use Illuminate\Http\Request;
use Telegram\Bot\Api as TelegramApi;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class BotSetupController extends Controller
{
    public function register(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $tenant = auth()->user()->tenant;

        // Create Telegram API with SSL fix for local development
        $caPath = base_path('cacert.pem');
        $verify = file_exists($caPath) ? $caPath : false;
        $httpClient = new GuzzleHttpClient(new \GuzzleHttp\Client(['verify' => $verify]));
        $telegram = new TelegramApi($request->token, false, $httpClient);
        $botInfo   = $telegram->getMe();

        // Generate unique webhook token for this tenant
        $webhookToken = hash('sha256', $tenant->id . $request->token . config('app.key'));
        $webhookUrl   = url("/api/telegram/webhook/{$webhookToken}");

        // Store bot token
        BotToken::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'token'        => $request->token,
                'bot_username' => $botInfo->getUsername(),
                'bot_name'     => $botInfo->getFirstName(),
                'webhook_url'  => $webhookUrl,
                'webhook_token' => $webhookToken,
            ]
        );

        // Register webhook with Telegram (requires HTTPS — skip on local dev)
        if (str_starts_with($webhookUrl, 'https://')) {
            $telegram->setWebhook([
                'url'             => $webhookUrl,
                'secret_token'    => $webhookToken,
                'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
            ]);
            BotToken::where('tenant_id', $tenant->id)->update(['webhook_set' => true]);
        }

        $message = "Bot @{$botInfo->getUsername()} connected successfully!";
        if (!str_starts_with($webhookUrl, 'https://')) {
            $message .= ' Webhook skipped (HTTPS required — will be set on production).';
        }

        return back()->with('success', $message);
    }

    /**
     * Generate a one-time link code for the admin to connect Telegram.
     */
    public function generateLinkCode()
    {
        $user = auth()->user();
        $code = AdminGuard::generateLinkCode($user);

        return back()->with('link_code', $code)
            ->with('success', "Your link code is: {$code} — valid for 10 minutes. Type /link {$code} in your Telegram group.");
    }

    /**
     * Unlink the Telegram account.
     */
    public function unlinkTelegram()
    {
        auth()->user()->update([
            'telegram_user_id'         => null,
            'telegram_link_code'       => null,
            'telegram_link_expires_at' => null,
        ]);

        return back()->with('success', 'Telegram account unlinked.');
    }
}

