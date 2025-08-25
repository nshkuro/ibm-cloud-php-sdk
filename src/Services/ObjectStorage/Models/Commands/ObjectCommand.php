<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\Models\Commands;

use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Services\ObjectStorage\ValueObjects\StorageClass;

/**
 * Command for object operations (store, delete).
 */
final class ObjectCommand
{
    public function __construct(
        public BucketName $bucket,
        public ObjectKey $key,
        public ?string $content = null,
        public ?StorageClass $storageClass = null,
        public array $metadata = [],
        public ?string $contentType = null
    ) {
    }

    /**
     * Create a store command.
     */
    public static function store(
        BucketName $bucket,
        ObjectKey $key,
        string $content,
        ?StorageClass $storageClass = null,
        array $metadata = [],
        ?string $contentType = null
    ): self {
        return new self($bucket, $key, $content, $storageClass, $metadata, $contentType);
    }

    /**
     * Create a delete command.
     */
    public static function delete(BucketName $bucket, ObjectKey $key): self
    {
        return new self($bucket, $key);
    }

    /**
     * Check if this is a store operation.
     */
    public function isStore(): bool
    {
        return $this->content !== null;
    }

    /**
     * Check if this is a delete operation.
     */
    public function isDelete(): bool
    {
        return $this->content === null;
    }
}