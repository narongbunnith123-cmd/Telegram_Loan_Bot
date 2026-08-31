<?php

namespace App\Services\Telegram\Conversations;

/**
 * Represents the result of processing a conversation step.
 * Tells the ConversationManager what to do next.
 */
class ConversationResult
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $nextStep,
        public readonly ?string $message,
        public readonly ?array $keyboard,
        public readonly ?array $inlineKeyboard,
        public readonly array $mergeData,
        public readonly bool $endConversation,
    ) {
    }

    /**
     * Advance to the next step with a prompt message.
     */
    public static function advance(
        string $nextStep,
        string $message,
        ?array $keyboard = null,
        ?array $inlineKeyboard = null,
        array $mergeData = [],
    ): self {
        return new self(
            type: 'advance',
            nextStep: $nextStep,
            message: $message,
            keyboard: $keyboard,
            inlineKeyboard: $inlineKeyboard,
            mergeData: $mergeData,
            endConversation: false,
        );
    }

    /**
     * Stay on the current step (validation error, re-prompt).
     */
    public static function stay(string $message, ?array $keyboard = null, ?array $inlineKeyboard = null): self
    {
        return new self(
            type: 'stay',
            nextStep: null,
            message: $message,
            keyboard: $keyboard,
            inlineKeyboard: $inlineKeyboard,
            mergeData: [],
            endConversation: false,
        );
    }

    /**
     * Continue without sending a message (step handled internally).
     */
    public static function continue(?string $step = null): self
    {
        return new self(
            type: 'continue',
            nextStep: $step,
            message: null,
            keyboard: null,
            inlineKeyboard: null,
            mergeData: [],
            endConversation: false,
        );
    }

    /**
     * End the conversation successfully with a final message.
     */
    public static function complete(string $message, bool $showMenu = true): self
    {
        return new self(
            type: 'complete',
            nextStep: null,
            message: $message,
            keyboard: null,
            inlineKeyboard: null,
            mergeData: [],
            endConversation: true,
        );
    }

    /**
     * End the conversation due to an error.
     */
    public static function error(string $message): self
    {
        return new self(
            type: 'error',
            nextStep: null,
            message: $message,
            keyboard: null,
            inlineKeyboard: null,
            mergeData: [],
            endConversation: true,
        );
    }
}
