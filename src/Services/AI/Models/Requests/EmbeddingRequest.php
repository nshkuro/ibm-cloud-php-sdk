<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Requests;

use IBMCloud\Services\AI\ValueObjects\ModelId;
use InvalidArgumentException;

final class EmbeddingRequest
{
    public function __construct(
        private readonly ModelId $modelId,
        private readonly array $inputs,
        private readonly ?string $projectId = null,
        private readonly ?string $spaceId = null,
        private readonly ?array $parameters = null
    ) {
        $this->validateRequest();
    }

    public static function create(ModelId $modelId, array $inputs): self
    {
        return new self($modelId, $inputs);
    }

    public static function single(ModelId $modelId, string $text): self
    {
        return new self($modelId, [$text]);
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
            $this->inputs,
            $projectId,
            $this->spaceId,
            $this->parameters
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
            $this->inputs,
            $this->projectId,
            $spaceId,
            $this->parameters
        );
    }

    /**
     * Set parameters for embedding generation.
     */
    public function withParameters(array $parameters): self
    {
        return new self(
            $this->modelId,
            $this->inputs,
            $this->projectId,
            $this->spaceId,
            $parameters
        );
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        $request = [
            'model_id' => $this->modelId->toString(),
            'inputs' => $this->inputs,
        ];

        if ($this->projectId !== null) {
            $request['project_id'] = $this->projectId;
        }

        if ($this->spaceId !== null) {
            $request['space_id'] = $this->spaceId;
        }

        if ($this->parameters !== null) {
            $request['parameters'] = $this->parameters;
        }

        return $request;
    }

    /**
     * Validate the request.
     */
    private function validateRequest(): void
    {
        if (empty($this->inputs)) {
            throw new InvalidArgumentException('Inputs array cannot be empty.');
        }

        if (count($this->inputs) > 100) {
            throw new InvalidArgumentException('Maximum 100 inputs allowed per request.');
        }

        foreach ($this->inputs as $input) {
            if (!is_string($input)) {
                throw new InvalidArgumentException('All inputs must be strings.');
            }

            if (empty($input)) {
                throw new InvalidArgumentException('Input strings cannot be empty.');
            }

            if (strlen($input) > 8192) {
                throw new InvalidArgumentException('Input strings cannot exceed 8,192 characters.');
            }
        }

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
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getSpaceId(): ?string
    {
        return $this->spaceId;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * Get total character count of all inputs.
     */
    public function getTotalCharacterCount(): int
    {
        return array_sum(array_map('strlen', $this->inputs));
    }

    /**
     * Get estimated token count for all inputs.
     */
    public function getEstimatedTokenCount(): int
    {
        return (int) ceil($this->getTotalCharacterCount() / 4);
    }

    /**
     * Check if this is a single input request.
     */
    public function isSingleInput(): bool
    {
        return count($this->inputs) === 1;
    }

    /**
     * Get the single input text (for single input requests).
     */
    public function getSingleInput(): string
    {
        if (!$this->isSingleInput()) {
            throw new InvalidArgumentException('This is not a single input request.');
        }

        return $this->inputs[0];
    }
}