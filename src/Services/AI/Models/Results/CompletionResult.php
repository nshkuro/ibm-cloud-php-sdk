<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

use IBMCloud\Services\AI\ValueObjects\ModelId;
use DateTimeImmutable;

final class CompletionResult
{
    public function __construct(
        private readonly ModelId $modelId,
        private readonly DateTimeImmutable $createdAt,
        private readonly array $results,
        private readonly ?array $system = null
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            ModelId::from($response['model_id']),
            new DateTimeImmutable($response['created_at']),
            $response['results'],
            $response['system'] ?? null
        );
    }

    /**
     * Get the first (primary) generated text.
     */
    public function getText(): string
    {
        if (empty($this->results)) {
            return '';
        }

        return $this->results[0]['generated_text'] ?? '';
    }

    /**
     * Get all generated text results.
     */
    public function getAllTexts(): array
    {
        return array_map(
            fn(array $result) => $result['generated_text'] ?? '',
            $this->results
        );
    }

    /**
     * Get the stop reason for the first result.
     */
    public function getStopReason(): ?string
    {
        if (empty($this->results)) {
            return null;
        }

        return $this->results[0]['stop_reason'] ?? null;
    }

    /**
     * Get all stop reasons.
     */
    public function getAllStopReasons(): array
    {
        return array_map(
            fn(array $result) => $result['stop_reason'] ?? null,
            $this->results
        );
    }

    /**
     * Get the number of generated tokens for the first result.
     */
    public function getGeneratedTokenCount(): int
    {
        if (empty($this->results)) {
            return 0;
        }

        return $this->results[0]['generated_token_count'] ?? 0;
    }

    /**
     * Get the number of input tokens consumed.
     */
    public function getInputTokenCount(): int
    {
        if (empty($this->results)) {
            return 0;
        }

        return $this->results[0]['input_token_count'] ?? 0;
    }

    /**
     * Get total token count (input + generated).
     */
    public function getTotalTokenCount(): int
    {
        return $this->getInputTokenCount() + $this->getGeneratedTokenCount();
    }

    /**
     * Get the seed used for generation (if any).
     */
    public function getSeed(): ?int
    {
        if (empty($this->results)) {
            return null;
        }

        return $this->results[0]['seed'] ?? null;
    }

    /**
     * Get generated token information.
     */
    public function getGeneratedTokens(): array
    {
        if (empty($this->results)) {
            return [];
        }

        return $this->results[0]['generated_tokens'] ?? [];
    }

    /**
     * Get input token information.
     */
    public function getInputTokens(): array
    {
        if (empty($this->results)) {
            return [];
        }

        return $this->results[0]['input_tokens'] ?? [];
    }

    /**
     * Get moderation results.
     */
    public function getModerations(): ?array
    {
        if (empty($this->results)) {
            return null;
        }

        return $this->results[0]['moderations'] ?? null;
    }

    /**
     * Check if content was flagged by moderation.
     */
    public function isFlagged(): bool
    {
        $moderations = $this->getModerations();
        
        if ($moderations === null) {
            return false;
        }

        // Check HAP (Hate, Abuse, Profanity) flag
        if (isset($moderations['hap']['flagged']) && $moderations['hap']['flagged']) {
            return true;
        }

        // Check PII (Personally Identifiable Information) flag
        if (isset($moderations['pii']['flagged']) && $moderations['pii']['flagged']) {
            return true;
        }

        return false;
    }

    /**
     * Get model version used.
     */
    public function getModelVersion(): ?string
    {
        if (empty($this->results)) {
            return null;
        }

        return $this->results[0]['model_version'] ?? null;
    }

    /**
     * Check if generation was completed successfully.
     */
    public function isCompleted(): bool
    {
        $stopReason = $this->getStopReason();
        
        return in_array($stopReason, [
            'eos_token',      // End of sequence token
            'stop_sequence',  // Custom stop sequence
            'max_tokens',     // Reached token limit
        ], true);
    }

    /**
     * Check if generation was truncated due to limits.
     */
    public function wasTruncated(): bool
    {
        return $this->getStopReason() === 'max_tokens';
    }

    /**
     * Check if generation was cancelled.
     */
    public function wasCancelled(): bool
    {
        return in_array($this->getStopReason(), ['cancelled', 'time_limit'], true);
    }

    /**
     * Check if there was an error during generation.
     */
    public function hasError(): bool
    {
        return $this->getStopReason() === 'error';
    }

    /**
     * Get quality score (based on various metrics).
     */
    public function getQualityScore(): float
    {
        $score = 1.0;
        
        // Reduce score if flagged
        if ($this->isFlagged()) {
            $score *= 0.3;
        }
        
        // Reduce score if truncated
        if ($this->wasTruncated()) {
            $score *= 0.8;
        }
        
        // Reduce score if cancelled or error
        if ($this->wasCancelled() || $this->hasError()) {
            $score *= 0.1;
        }
        
        return $score;
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getSystem(): ?array
    {
        return $this->system;
    }

    /**
     * Get formatted summary of the result.
     */
    public function getSummary(): array
    {
        return [
            'model_id' => $this->modelId->toString(),
            'generated_text_length' => strlen($this->getText()),
            'input_tokens' => $this->getInputTokenCount(),
            'generated_tokens' => $this->getGeneratedTokenCount(),
            'total_tokens' => $this->getTotalTokenCount(),
            'stop_reason' => $this->getStopReason(),
            'flagged' => $this->isFlagged(),
            'completed' => $this->isCompleted(),
            'quality_score' => $this->getQualityScore(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}