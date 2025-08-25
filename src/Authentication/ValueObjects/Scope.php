<?php

declare(strict_types=1);

namespace IBMCloud\Authentication\ValueObjects;

/**
 * Immutable value object representing authentication scope.
 */
final class Scope
{
    public function __construct(
        public string $service,
        public string $region = 'global'
    ) {
        if (empty($this->service)) {
            throw new \InvalidArgumentException('Service name cannot be empty.');
        }
        
        if (empty($this->region)) {
            throw new \InvalidArgumentException('Region cannot be empty.');
        }
    }

    /**
     * Create scope for Object Storage service.
     */
    public static function objectStorage(string $region = 'global'): self
    {
        return new self('cloud-object-storage', $region);
    }

    /**
     * Create scope for WatsonX AI service.
     */
    public static function watsonxAi(string $region = 'us-south'): self
    {
        return new self('watsonx-ai', $region);
    }


    /**
     * Create global scope for all services.
     */
    public static function global(): self
    {
        return new self('*', 'global');
    }

    /**
     * Get unique identifier for this scope.
     */
    public function getIdentifier(): string
    {
        return sprintf('%s:%s', $this->service, $this->region);
    }

    /**
     * Check if this scope matches another scope.
     */
    public function matches(self $other): bool
    {
        return $this->service === $other->service && $this->region === $other->region;
    }

    /**
     * Check if this is a global scope.
     */
    public function isGlobal(): bool
    {
        return $this->service === '*' || $this->region === 'global';
    }
}