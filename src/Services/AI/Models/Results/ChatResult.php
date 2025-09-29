<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

use DateTimeImmutable;
use IBMCloud\Services\AI\ValueObjects\ModelId;

final class ChatResult
{
    /** @var ChatChoice[] */
    private readonly array $choices;

    public function __construct(
        private readonly string $id,
        private readonly ModelId $modelId,
        private readonly int $created,
        array $choices,
        private readonly ?ChatUsage $usage = null,
        private readonly ?string $modelVersion = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?array $system = null
    ) {
        $this->choices = $this->parseChoices($choices);
    }

    /**
     * Parse choices from API response.
     */
    private function parseChoices(array $choices): array
    {
        $parsed = [];
        foreach ($choices as $choice) {
            $parsed[] = $choice instanceof ChatChoice
                ? $choice
                : ChatChoice::fromApiResponse($choice);
        }
        return $parsed;
    }

    /**
     * Create from API response.
     */
    public static function fromApiResponse(array $data): self
    {
        $modelId = ModelId::from($data['model_id'] ?? $data['model'] ?? 'unknown');

        $usage = isset($data['usage'])
            ? ChatUsage::fromApiResponse($data['usage'])
            : null;

        $createdAt = isset($data['created_at'])
            ? new DateTimeImmutable($data['created_at'])
            : null;

        return new self(
            $data['id'],
            $modelId,
            $data['created'] ?? time(),
            $data['choices'] ?? [],
            $usage,
            $data['model_version'] ?? null,
            $createdAt,
            $data['system'] ?? null
        );
    }

    /**
     * Get the completion ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the model ID.
     */
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    /**
     * Get the creation timestamp.
     */
    public function getCreated(): int
    {
        return $this->created;
    }

    /**
     * Get the choices.
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Get the first choice (most common case).
     */
    public function getFirstChoice(): ?ChatChoice
    {
        return $this->choices[0] ?? null;
    }

    /**
     * Get the content of the first choice.
     */
    public function getContent(): ?string
    {
        $choice = $this->getFirstChoice();
        return $choice?->getMessage()->getText();
    }

    /**
     * Get usage information.
     */
    public function getUsage(): ?ChatUsage
    {
        return $this->usage;
    }

    /**
     * Get the model version.
     */
    public function getModelVersion(): ?string
    {
        return $this->modelVersion;
    }

    /**
     * Get the creation date/time.
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get system information.
     */
    public function getSystem(): ?array
    {
        return $this->system;
    }

    /**
     * Check if the response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        foreach ($this->choices as $choice) {
            if ($choice->hasToolCalls()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all tool calls from all choices.
     */
    public function getAllToolCalls(): array
    {
        $toolCalls = [];
        foreach ($this->choices as $choice) {
            $calls = $choice->getToolCalls();
            if ($calls !== null) {
                $toolCalls = array_merge($toolCalls, $calls);
            }
        }
        return $toolCalls;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'model_id' => $this->modelId->toString(),
            'created' => $this->created,
            'choices' => array_map(fn(ChatChoice $c) => $c->toArray(), $this->choices),
        ];

        if ($this->usage !== null) {
            $result['usage'] = $this->usage->toArray();
        }

        if ($this->modelVersion !== null) {
            $result['model_version'] = $this->modelVersion;
        }

        if ($this->createdAt !== null) {
            $result['created_at'] = $this->createdAt->format('c');
        }

        if ($this->system !== null) {
            $result['system'] = $this->system;
        }

        return $result;
    }
}