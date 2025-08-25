<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\Models\Queries;

use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;

/**
 * Query for object retrieval operations.
 */
final class ObjectQuery
{
    public function __construct(
        public BucketName $bucket,
        public ObjectKey $key,
        public bool $includeMetadata = false,
        public ?int $rangeStart = null,
        public ?int $rangeEnd = null
    ) {
        if ($this->rangeStart !== null && $this->rangeStart < 0) {
            throw new \InvalidArgumentException('Range start must be non-negative.');
        }
        
        if ($this->rangeEnd !== null && $this->rangeEnd < 0) {
            throw new \InvalidArgumentException('Range end must be non-negative.');
        }
        
        if ($this->rangeStart !== null && $this->rangeEnd !== null && $this->rangeStart > $this->rangeEnd) {
            throw new \InvalidArgumentException('Range start must be less than or equal to range end.');
        }
    }

    /**
     * Create a simple query for an object.
     */
    public static function for(BucketName $bucket, ObjectKey $key): self
    {
        return new self($bucket, $key);
    }

    /**
     * Create a query with metadata included.
     */
    public static function withMetadata(BucketName $bucket, ObjectKey $key): self
    {
        return new self($bucket, $key, true);
    }

    /**
     * Create a range query for partial object retrieval.
     */
    public static function withRange(
        BucketName $bucket,
        ObjectKey $key,
        int $rangeStart,
        ?int $rangeEnd = null
    ): self {
        return new self($bucket, $key, false, $rangeStart, $rangeEnd);
    }

    /**
     * Check if this is a range query.
     */
    public function hasRange(): bool
    {
        return $this->rangeStart !== null;
    }

    /**
     * Get the range header value for HTTP requests.
     */
    public function getRangeHeader(): ?string
    {
        if (!$this->hasRange()) {
            return null;
        }
        
        $end = $this->rangeEnd ?? '';
        return sprintf('bytes=%d-%s', $this->rangeStart, $end);
    }
}