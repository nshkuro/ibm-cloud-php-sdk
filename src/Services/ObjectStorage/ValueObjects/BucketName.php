<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\ValueObjects;

/**
 * Immutable value object representing a COS bucket name.
 */
final class BucketName
{
    public function __construct(
        public string $value
    ) {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('Bucket name cannot be empty.');
        }
        
        if (!$this->isValidBucketName($this->value)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid bucket name: %s', $this->value)
            );
        }
    }

    /**
     * Create from string.
     */
    public static function from(string $bucketName): self
    {
        return new self($bucketName);
    }

    /**
     * Get the bucket name as string.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Validate bucket name according to IBM COS rules.
     */
    private function isValidBucketName(string $name): bool
    {
        // IBM COS bucket naming rules:
        // - 3-63 characters
        // - lowercase letters, numbers, dots, hyphens
        // - cannot start/end with dot or hyphen
        // - cannot contain consecutive dots
        // - cannot be formatted as IP address
        
        if (strlen($name) < 3 || strlen($name) > 63) {
            return false;
        }
        
        if (!preg_match('/^[a-z0-9.-]+$/', $name)) {
            return false;
        }
        
        if (str_starts_with($name, '.') || str_starts_with($name, '-')) {
            return false;
        }
        
        if (str_ends_with($name, '.') || str_ends_with($name, '-')) {
            return false;
        }
        
        if (str_contains($name, '..')) {
            return false;
        }
        
        // Check if it looks like an IP address.
        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $name)) {
            return false;
        }
        
        return true;
    }
}