<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Result of a text extraction request.
 */
final class TextExtractionResult
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly string $id,
        private readonly string $status,
        private readonly ?string $projectId = null,
        private readonly ?string $spaceId = null,
        private readonly ?string $name = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $runningAt = null,
        private readonly ?DateTimeImmutable $completedAt = null,
        private readonly ?int $numberPagesProcessed = null,
        private readonly ?array $documentReference = null,
        private readonly ?array $resultsReference = null,
        private readonly ?array $parameters = null,
        private readonly ?array $custom = null,
        private readonly ?array $results = null
    ) {
        if (empty($id)) {
            throw new InvalidArgumentException('ID cannot be empty');
        }

        if (empty($status)) {
            throw new InvalidArgumentException('Status cannot be empty');
        }
    }

    /**
     * Create result from API response.
     */
    public static function fromApiResponse(array $data): self
    {
        $metadata = $data['metadata'] ?? [];
        $entity = $data['entity'] ?? [];
        $results = $entity['results'] ?? [];

        $createdAt = isset($metadata['created_at']) 
            ? new DateTimeImmutable($metadata['created_at'])
            : null;

        $runningAt = isset($results['running_at']) 
            ? new DateTimeImmutable($results['running_at'])
            : null;

        $completedAt = isset($results['completed_at']) 
            ? new DateTimeImmutable($results['completed_at'])
            : null;

        return new self(
            id: $metadata['id'],
            status: $results['status'] ?? 'unknown',
            projectId: $metadata['project_id'] ?? null,
            spaceId: $metadata['space_id'] ?? null,
            name: $metadata['name'] ?? null,
            createdAt: $createdAt,
            runningAt: $runningAt,
            completedAt: $completedAt,
            numberPagesProcessed: $results['number_pages_processed'] ?? null,
            documentReference: $entity['document_reference'] ?? null,
            resultsReference: $entity['results_reference'] ?? null,
            parameters: $entity['parameters'] ?? null,
            custom: $entity['custom'] ?? null,
            results: $results
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getSpaceId(): ?string
    {
        return $this->spaceId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRunningAt(): ?DateTimeImmutable
    {
        return $this->runningAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getNumberPagesProcessed(): ?int
    {
        return $this->numberPagesProcessed;
    }

    public function getDocumentReference(): ?array
    {
        return $this->documentReference;
    }

    public function getResultsReference(): ?array
    {
        return $this->resultsReference;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function getCustom(): ?array
    {
        return $this->custom;
    }

    public function getResults(): ?array
    {
        return $this->results;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED
        ]);
    }

    /**
     * Get processing duration in seconds.
     */
    public function getProcessingDuration(): ?float
    {
        if ($this->runningAt === null) {
            return null;
        }

        $endTime = $this->completedAt ?? new DateTimeImmutable();
        return $endTime->getTimestamp() - $this->runningAt->getTimestamp();
    }

    /**
     * Get total duration from creation to completion.
     */
    public function getTotalDuration(): ?float
    {
        if ($this->createdAt === null) {
            return null;
        }

        $endTime = $this->completedAt ?? new DateTimeImmutable();
        return $endTime->getTimestamp() - $this->createdAt->getTimestamp();
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'project_id' => $this->projectId,
            'space_id' => $this->spaceId,
            'name' => $this->name,
            'created_at' => $this->createdAt?->format('c'),
            'running_at' => $this->runningAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
            'number_pages_processed' => $this->numberPagesProcessed,
            'document_reference' => $this->documentReference,
            'results_reference' => $this->resultsReference,
            'parameters' => $this->parameters,
            'custom' => $this->custom,
            'processing_duration_seconds' => $this->getProcessingDuration(),
            'total_duration_seconds' => $this->getTotalDuration(),
            'results' => $this->results,
        ];
    }
}