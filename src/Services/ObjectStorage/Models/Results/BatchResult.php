<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\Models\Results;

/**
 * Result of a batch operation.
 */
final class BatchResult
{
    /** @var ObjectResult[] */
    public array $successful;
    
    /** @var array<string, \Throwable> */
    public array $failed;

    /**
     * @param ObjectResult[] $successful
     * @param array<string, \Throwable> $failed Key is bucket:object identifier
     */
    public function __construct(array $successful = [], array $failed = [])
    {
        $this->successful = array_values($successful);
        $this->failed = $failed;
    }

    /**
     * Get the total number of operations attempted.
     */
    public function getTotalCount(): int
    {
        return $this->getSuccessfulCount() + $this->getFailedCount();
    }

    /**
     * Get the number of successful operations.
     */
    public function getSuccessfulCount(): int
    {
        return count($this->successful);
    }

    /**
     * Get the number of failed operations.
     */
    public function getFailedCount(): int
    {
        return count($this->failed);
    }

    /**
     * Check if all operations were successful.
     */
    public function isFullySuccessful(): bool
    {
        return empty($this->failed);
    }

    /**
     * Check if any operations were successful.
     */
    public function hasSuccesses(): bool
    {
        return !empty($this->successful);
    }

    /**
     * Check if any operations failed.
     */
    public function hasFailures(): bool
    {
        return !empty($this->failed);
    }

    /**
     * Get all failures as an array of key => exception.
     * 
     * @return array<string, \Throwable>
     */
    public function getFailures(): array
    {
        return $this->failed;
    }
}