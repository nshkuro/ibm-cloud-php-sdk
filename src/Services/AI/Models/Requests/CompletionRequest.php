<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Requests;

use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;
use InvalidArgumentException;

class CompletionRequest
{
    public function __construct(
        private readonly ModelId $modelId,
        private readonly Prompt $prompt,
        private readonly ?string $projectId = null,
        private readonly ?string $spaceId = null,
        private readonly ?GenerationParameters $parameters = null,
        private readonly ?array $moderations = null,
        private readonly ?array $metadata = null
    ) {
        // Only validate if we have project/space ID set
        if ($this->projectId !== null || $this->spaceId !== null) {
            $this->validateRequest();
        }
    }

    public static function create(ModelId $modelId, Prompt $prompt): self
    {
        return new self($modelId, $prompt);
    }

    /**
     * Set project ID for the request.
     */
    public function withProjectId(string $projectId): self
    {
        if (empty($projectId)) {
            throw new InvalidArgumentException('Project ID cannot be empty.');
        }

        return new self(
            $this->modelId,
            $this->prompt,
            $projectId,
            $this->spaceId,
            $this->parameters,
            $this->moderations,
            $this->metadata
        );
    }

    /**
     * Set space ID for the request.
     */
    public function withSpaceId(string $spaceId): self
    {
        if (empty($spaceId)) {
            throw new InvalidArgumentException('Space ID cannot be empty.');
        }

        return new self(
            $this->modelId,
            $this->prompt,
            $this->projectId,
            $spaceId,
            $this->parameters,
            $this->moderations,
            $this->metadata
        );
    }

    /**
     * Set generation parameters.
     */
    public function withParameters(GenerationParameters $parameters): self
    {
        return new self(
            $this->modelId,
            $this->prompt,
            $this->projectId,
            $this->spaceId,
            $parameters,
            $this->moderations,
            $this->metadata
        );
    }

    /**
     * Set content moderation settings.
     */
    public function withModerations(array $moderations): self
    {
        return new self(
            $this->modelId,
            $this->prompt,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            $moderations,
            $this->metadata
        );
    }

    /**
     * Enable HAP (Hate, Abuse, and Profanity) detection.
     */
    public function withHAPDetection(bool $enabled = true, float $threshold = 0.5): self
    {
        $moderations = $this->moderations ?? [];
        
        $moderations['hap'] = [
            'output' => [
                'enabled' => $enabled,
                'threshold' => $threshold,
            ],
        ];

        return $this->withModerations($moderations);
    }

    /**
     * Enable PII (Personally Identifiable Information) detection.
     */
    public function withPIIDetection(bool $enabled = true, bool $maskEntities = true): self
    {
        $moderations = $this->moderations ?? [];
        
        $moderations['pii'] = [
            'output' => [
                'enabled' => $enabled,
            ],
            'mask' => [
                'remove_entity_value' => $maskEntities,
            ],
        ];

        return $this->withModerations($moderations);
    }

    /**
     * Set custom metadata.
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->modelId,
            $this->prompt,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            $this->moderations,
            $metadata
        );
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        // Validate before converting to array
        $this->validateRequest();
        
        $request = [
            'model_id' => $this->modelId->toString(),
            'input' => $this->prompt->toString(),
        ];

        if ($this->projectId !== null) {
            $request['project_id'] = $this->projectId;
        }

        if ($this->spaceId !== null) {
            $request['space_id'] = $this->spaceId;
        }

        if ($this->parameters !== null) {
            $request['parameters'] = $this->parameters->toArray();
        }

        if ($this->moderations !== null) {
            $request['moderations'] = $this->moderations;
        }

        return $request;
    }

    /**
     * Validate the request.
     */
    private function validateRequest(): void
    {
        if ($this->projectId === null && $this->spaceId === null) {
            throw new InvalidArgumentException(
                'Either project_id or space_id must be provided.'
            );
        }

        if ($this->projectId !== null && $this->spaceId !== null) {
            throw new InvalidArgumentException(
                'Cannot specify both project_id and space_id. Choose one.'
            );
        }

        if ($this->prompt->hasSensitiveContent()) {
            throw new InvalidArgumentException(
                'Prompt appears to contain sensitive content. Please review before sending.'
            );
        }
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getSpaceId(): ?string
    {
        return $this->spaceId;
    }

    public function getParameters(): ?GenerationParameters
    {
        return $this->parameters;
    }

    public function getModerations(): ?array
    {
        return $this->moderations;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Get estimated cost in tokens.
     */
    public function getEstimatedInputTokens(): int
    {
        return $this->prompt->getEstimatedTokenCount();
    }

    /**
     * Get estimated maximum output tokens.
     */
    public function getEstimatedMaxOutputTokens(): int
    {
        return $this->parameters?->getMaxNewTokens() ?? 100;
    }

    /**
     * Get total estimated tokens (input + max output).
     */
    public function getEstimatedTotalTokens(): int
    {
        return $this->getEstimatedInputTokens() + $this->getEstimatedMaxOutputTokens();
    }
}