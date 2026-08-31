<?php

namespace App\Services\Telegram\Conversations;

use App\Models\TelegramSession;
use App\Models\Tenant;

/**
 * Base class for all conversation handlers.
 * Each conversation defines its flow steps and handles user input at each step.
 */
abstract class BaseConversation
{
    /**
     * Get the action name (matches ConversationManager action names).
     */
    abstract public static function action(): string;

    /**
     * Get the first step of this conversation.
     */
    abstract public function firstStep(): string;

    /**
     * Handle user input for the current step.
     * Returns a ConversationResult indicating what to do next.
     */
    abstract public function handleStep(
        Tenant $tenant,
        TelegramSession $session,
        string $input,
        array $message,
    ): ConversationResult;

    /**
     * Handle a callback query (inline button press) for the current step.
     * Override in subclass if the conversation uses inline buttons.
     */
    public function handleCallback(
        Tenant $tenant,
        TelegramSession $session,
        string $callbackData,
        array $callbackQuery,
    ): ConversationResult {
        // Default: ignore callbacks
        return ConversationResult::continue($session->current_step);
    }

    /**
     * Get the previous step for /back navigation.
     * Returns null if at the first step (should cancel).
     */
    abstract public function previousStep(string $currentStep): ?string;

    /**
     * Get the ordered list of steps.
     */
    abstract public function steps(): array;

    protected function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
