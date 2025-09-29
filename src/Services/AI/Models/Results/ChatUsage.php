<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

final class ChatUsage
{
    public function __construct(
        private readonly int $promptTokens,
        private readonly int $completionTokens,
        private readonly int $totalTokens
    ) {}

    /**
     * Create from API response.
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            $data['prompt_tokens'] ?? 0,
            $data['completion_tokens'] ?? 0,
            $data['total_tokens'] ?? 0
        );
    }

    /**
     * Get prompt tokens count.
     */
    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    /**
     * Get completion tokens count.
     */
    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    /**
     * Get total tokens count.
     */
    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}