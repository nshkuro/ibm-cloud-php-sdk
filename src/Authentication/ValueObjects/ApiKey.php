<?php

declare(strict_types=1);

namespace IBMCloud\Authentication\ValueObjects;

/**
 * Immutable value object representing an IBM Cloud API key.
 */
final readonly class ApiKey
{
    public function __construct(
        public string $value
    ) {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('API key cannot be empty.');
        }
        
        // Basic validation for IBM Cloud API key format.
        if (!$this->isValidFormat($this->value)) {
            throw new \InvalidArgumentException('Invalid IBM Cloud API key format.');
        }
    }

    /**
     * Create from environment variable.
     */
    public static function fromEnvironment(string $envVar = 'IBM_API_KEY'): self
    {
        $apiKey = $_ENV[$envVar] ?? getenv($envVar);
        
        if (!$apiKey || !is_string($apiKey)) {
            throw new \InvalidArgumentException(
                sprintf('API key not found in environment variable: %s', $envVar)
            );
        }
        
        return new self($apiKey);
    }

    /**
     * Mask the API key for logging (show only first and last 4 chars).
     */
    public function masked(): string
    {
        if (strlen($this->value) <= 8) {
            return str_repeat('*', strlen($this->value));
        }
        
        return substr($this->value, 0, 4) . 
               str_repeat('*', strlen($this->value) - 8) . 
               substr($this->value, -4);
    }

    /**
     * Basic validation of IBM Cloud API key format.
     */
    private function isValidFormat(string $apiKey): bool
    {
        // IBM Cloud API keys are typically alphanumeric with dashes and underscores.
        // They're usually around 44 characters long.
        return preg_match('/^[a-zA-Z0-9_-]+$/', $apiKey) === 1 
            && strlen($apiKey) >= 20 
            && strlen($apiKey) <= 100;
    }
}