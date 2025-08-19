<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\Models\Results;

use DateTimeInterface;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Services\ObjectStorage\ValueObjects\StorageClass;

/**
 * Result of an object storage operation.
 */
final readonly class ObjectResult
{
    public function __construct(
        public BucketName $bucket,
        public ObjectKey $key,
        public ?string $content = null,
        public ?string $contentType = null,
        public ?int $contentLength = null,
        public ?string $etag = null,
        public ?DateTimeInterface $lastModified = null,
        public ?StorageClass $storageClass = null,
        public array $metadata = []
    ) {
    }

    /**
     * Create result for a successful store operation.
     */
    public static function stored(
        BucketName $bucket,
        ObjectKey $key,
        string $etag,
        ?StorageClass $storageClass = null
    ): self {
        return new self(
            bucket: $bucket,
            key: $key,
            etag: $etag,
            storageClass: $storageClass
        );
    }

    /**
     * Create result for a successful retrieve operation.
     */
    public static function retrieved(
        BucketName $bucket,
        ObjectKey $key,
        string $content,
        ?string $contentType = null,
        ?string $etag = null,
        ?DateTimeInterface $lastModified = null,
        array $metadata = []
    ): self {
        return new self(
            bucket: $bucket,
            key: $key,
            content: $content,
            contentType: $contentType,
            contentLength: strlen($content),
            etag: $etag,
            lastModified: $lastModified,
            metadata: $metadata
        );
    }

    /**
     * Check if this result contains content.
     */
    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    /**
     * Get content as string, throwing if no content.
     */
    public function getContent(): string
    {
        if ($this->content === null) {
            throw new \RuntimeException('Object result does not contain content.');
        }
        
        return $this->content;
    }

    /**
     * Get the size of the content in bytes.
     */
    public function getSize(): int
    {
        return $this->contentLength ?? 0;
    }

    /**
     * Check if metadata is available.
     */
    public function hasMetadata(): bool
    {
        return !empty($this->metadata);
    }
}