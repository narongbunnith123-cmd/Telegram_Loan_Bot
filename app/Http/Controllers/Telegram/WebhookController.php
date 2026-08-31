<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\ProcessTelegramUpdate;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $botToken = $request->attributes->get('bot_token');
        $payload  = $request->all();

        // Log every incoming update
        WebhookLog::create([
            'tenant_id'  => $botToken->tenant_id,
            'update_id'  => data_get($payload, 'update_id'),
            'type'       => $this->detectType($payload),
            'payload'    => $payload,
            'status'     => 'received',
            'received_at' => now(),
        ]);

        // Dispatch to queue — never block the webhook
        ProcessTelegramUpdate::dispatch($botToken->tenant_id, $payload)
            ->onQueue('telegram');

        return response()->json(['ok' => true]);
    }

    private function detectType(array $payload): string
    {
        foreach (['message', 'callback_query', 'edited_message', 'my_chat_member'] as $type) {
            if (isset($payload[$type])) return $type;
        }
        return 'unknown';
    }
}
