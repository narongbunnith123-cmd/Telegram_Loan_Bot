<?php

namespace App\Jobs\Telegram;

use App\Models\BotToken;
use App\Models\Tenant;
use App\Services\Telegram\CommandRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        private int $tenantId,
        private array $payload
    ) {}

    public function handle(CommandRouter $router): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $router->route($tenant, $this->payload);
    }
}
