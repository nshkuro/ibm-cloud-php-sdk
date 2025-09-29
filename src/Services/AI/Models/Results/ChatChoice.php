<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

use IBMCloud\Services\AI\Models\ChatToolCall;
use IBMCloud\Services\AI\ValueObjects\ChatMessage;

final class ChatChoice
{
    /** @var ChatToolCall[]|null */
    private readonly ?array $toolCalls;

    public function __construct(
        private readonly int $index,
        private readonly ChatMessage $message,
        private readonly ?string $finishReason,
        ?array $toolCalls = null
    ) {
        $this->toolCalls = $this->parseToolCalls($toolCalls);
    }

    /**
     * Parse tool calls from API response.
     */
    private function parseToolCalls(?array $toolCalls): ?array
    {
        if ($toolCalls === null || empty($toolCalls)) {
            return null;
        }

        $parsed = [];
        foreach ($toolCalls as $toolCall) {
            $parsed[] = ChatToolCall::fromArray($toolCall);
        }
        return $parsed;
    }

    /**
     * Create from API response.
     */
    public static function fromApiResponse(array $data): self
    {
        $messageData = $data['message'] ?? [];

        // Handle tool calls in the message.
        $toolCalls = $messageData['tool_calls'] ?? null;

        // Create message from response data.
        $message = ChatMessage::fromArray($messageData);

        return new self(
            $data['index'] ?? 0,
            $message,
            $data['finish_reason'] ?? null,
            $toolCalls
        );
    }

    /**
     * Get the choice index.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Get the message.
     */
    public function getMessage(): ChatMessage
    {
        return $this->message;
    }

    /**
     * Get the finish reason.
     */
    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    /**
     * Get tool calls if any.
     */
    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    /**
     * Check if this choice has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== null && !empty($this->toolCalls);
    }

    /**
     * Check if the response is complete.
     */
    public function isComplete(): bool
    {
        return $this->finishReason === 'stop';
    }

    /**
     * Check if the response hit the token limit.
     */
    public function isTokenLimit(): bool
    {
        return $this->finishReason === 'length';
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        $result = [
            'index' => $this->index,
            'message' => $this->message->toArray(),
        ];

        if ($this->finishReason !== null) {
            $result['finish_reason'] = $this->finishReason;
        }

        if ($this->toolCalls !== null) {
            $result['tool_calls'] = array_map(
                fn(ChatToolCall $call) => $call->toArray(),
                $this->toolCalls
            );
        }

        return $result;
    }
}