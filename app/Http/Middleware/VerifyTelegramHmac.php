<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTelegramHmac
{
    public function handle(Request $request, Closure $next)
    {
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // Find tenant by URL token (a hashed version stored in DB)
        $botToken = \App\Models\BotToken::where(
            'webhook_url', 'like', '%' . $request->route('tenantToken') . '%'
        )->first();

        if (!$botToken || !hash_equals($request->route('tenantToken'), $botToken->webhook_token)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Store resolved tenant in request for controller
        $request->attributes->set('bot_token', $botToken);

        return $next($request);
    }
}
