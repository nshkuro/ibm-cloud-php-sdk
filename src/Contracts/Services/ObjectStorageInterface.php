<?php

declare(strict_types=1);

namespace IBMCloud\Contracts\Services;

use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\Models\Commands\BatchCommand;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\Models\Results\ObjectResult;
use IBMCloud\Services\ObjectStorage\Models\Results\BatchResult;
use Psr\Http\Message\StreamInterface;

/**
 * Interface for IBM Cloud Object Storage operations.
 */
interface ObjectStorageInterface
{
    /**
     * Store an object in the bucket.
     */
    public function store(ObjectCommand $command): ObjectResult;

    /**
     * Retrieve an object from the bucket.
     */
    public function retrieve(ObjectQuery $query): ObjectResult;

    /**
     * Delete an object from the bucket.
     */
    public function delete(ObjectCommand $command): void;

    /**
     * Check if an object exists in the bucket.
     */
    public function exists(ObjectQuery $query): bool;

    /**
     * Stream an object from the bucket.
     */
    public function stream(ObjectQuery $query): StreamInterface;

    /**
     * Perform batch operations on multiple objects.
     */
    public function batch(BatchCommand $commands): BatchResult;
}