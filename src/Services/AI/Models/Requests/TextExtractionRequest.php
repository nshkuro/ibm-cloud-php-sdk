<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Requests;

use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionDataReference;
use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionParameters;
use InvalidArgumentException;

/**
 * Request for text extraction from documents.
 */
final class TextExtractionRequest
{
    public function __construct(
        private readonly TextExtractionDataReference $documentReference,
        private readonly TextExtractionDataReference $resultsReference,
        private readonly ?string $projectId = null,
        private readonly ?string $spaceId = null,
        private readonly ?TextExtractionParameters $parameters = null,
        private readonly ?array $custom = null
    ) {
        $this->validateProjectOrSpace();
    }

    /**
     * Create basic text extraction request.
     */
    public static function create(
        TextExtractionDataReference $documentReference,
        TextExtractionDataReference $resultsReference
    ): self {
        return new self($documentReference, $resultsReference);
    }

    /**
     * Set project ID.
     */
    public function withProjectId(string $projectId): self
    {
        if (empty($projectId)) {
            throw new InvalidArgumentException('Project ID cannot be empty');
        }

        return new self(
            documentReference: $this->documentReference,
            resultsReference: $this->resultsReference,
            projectId: $projectId,
            spaceId: $this->spaceId,
            parameters: $this->parameters,
            custom: $this->custom
        );
    }

    /**
     * Set space ID.
     */
    public function withSpaceId(string $spaceId): self
    {
        if (empty($spaceId)) {
            throw new InvalidArgumentException('Space ID cannot be empty');
        }

        return new self(
            documentReference: $this->documentReference,
            resultsReference: $this->resultsReference,
            projectId: $this->projectId,
            spaceId: $spaceId,
            parameters: $this->parameters,
            custom: $this->custom
        );
    }

    /**
     * Set extraction parameters.
     */
    public function withParameters(TextExtractionParameters $parameters): self
    {
        return new self(
            documentReference: $this->documentReference,
            resultsReference: $this->resultsReference,
            projectId: $this->projectId,
            spaceId: $this->spaceId,
            parameters: $parameters,
            custom: $this->custom
        );
    }

    /**
     * Set custom metadata.
     */
    public function withCustom(array $custom): self
    {
        return new self(
            documentReference: $this->documentReference,
            resultsReference: $this->resultsReference,
            projectId: $this->projectId,
            spaceId: $this->spaceId,
            parameters: $this->parameters,
            custom: $custom
        );
    }

    public function getDocumentReference(): TextExtractionDataReference
    {
        return $this->documentReference;
    }

    public function getResultsReference(): TextExtractionDataReference
    {
        return $this->resultsReference;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getSpaceId(): ?string
    {
        return $this->spaceId;
    }

    public function getParameters(): ?TextExtractionParameters
    {
        return $this->parameters;
    }

    public function getCustom(): ?array
    {
        return $this->custom;
    }

    /**
     * Convert to API request array.
     */
    public function toArray(): array
    {
        // Ensure we have project or space ID
        if ($this->projectId === null && $this->spaceId === null) {
            throw new InvalidArgumentException('Either project_id or space_id must be provided');
        }

        $data = [
            'document_reference' => $this->documentReference->toArray(),
            'results_reference' => $this->resultsReference->toArray(),
        ];

        if ($this->projectId !== null) {
            $data['project_id'] = $this->projectId;
        }

        if ($this->spaceId !== null) {
            $data['space_id'] = $this->spaceId;
        }

        if ($this->parameters !== null) {
            $data['parameters'] = $this->parameters->toArray();
        }

        if ($this->custom !== null) {
            $data['custom'] = $this->custom;
        }

        return $data;
    }

    private function validateProjectOrSpace(): void
    {
        if ($this->projectId !== null && $this->spaceId !== null) {
            throw new InvalidArgumentException('Cannot specify both project_id and space_id');
        }
    }
}