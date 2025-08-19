<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\ValueObjects;

/**
 * Immutable value object representing IBM COS storage classes.
 */
enum StorageClass: string
{
    case STANDARD = 'STANDARD';
    case VAULT = 'VAULT';
    case COLD = 'COLD';
    case FLEX = 'FLEX';
    case SMART = 'SMART';

    /**
     * Get storage class from string value.
     */
    public static function fromString(string $value): self
    {
        $normalizedValue = strtoupper($value);
        
        return match ($normalizedValue) {
            'STANDARD' => self::STANDARD,
            'VAULT' => self::VAULT,
            'COLD' => self::COLD,
            'FLEX' => self::FLEX,
            'SMART' => self::SMART,
            default => throw new \InvalidArgumentException(
                sprintf('Invalid storage class: %s', $value)
            ),
        };
    }

    /**
     * Get description of the storage class.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard storage for frequently accessed data',
            self::VAULT => 'Archive storage for infrequently accessed data',
            self::COLD => 'Cold storage for rarely accessed data',
            self::FLEX => 'Flexible storage that automatically tiers data',
            self::SMART => 'Smart tier that optimizes costs based on access patterns',
        };
    }

    /**
     * Check if this storage class supports immediate retrieval.
     */
    public function isImmediateAccess(): bool
    {
        return match ($this) {
            self::STANDARD, self::FLEX, self::SMART => true,
            self::VAULT, self::COLD => false,
        };
    }

    /**
     * Get minimum storage duration in days.
     */
    public function getMinimumStorageDays(): int
    {
        return match ($this) {
            self::STANDARD => 0,
            self::VAULT => 30,
            self::COLD => 90,
            self::FLEX => 0,
            self::SMART => 0,
        };
    }
}