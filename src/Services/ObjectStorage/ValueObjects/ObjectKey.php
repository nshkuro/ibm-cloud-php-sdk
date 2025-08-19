<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\ValueObjects;

/**
 * Immutable value object representing a COS object key (path).
 */
final readonly class ObjectKey
{
    public function __construct(
        public string $value
    ) {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('Object key cannot be empty.');
        }
        
        if (!$this->isValidObjectKey($this->value)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid object key: %s', $this->value)
            );
        }
    }

    /**
     * Create from string.
     */
    public static function from(string $objectKey): self
    {
        return new self($objectKey);
    }

    /**
     * Create from file path parts.
     */
    public static function fromPath(string ...$parts): self
    {
        $path = implode('/', array_filter($parts));
        return new self($path);
    }

    /**
     * Get the object key as string.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Get the directory part of the key.
     */
    public function getDirectory(): ?string
    {
        $lastSlash = strrpos($this->value, '/');
        
        if ($lastSlash === false) {
            return null;
        }
        
        return substr($this->value, 0, $lastSlash);
    }

    /**
     * Get the filename part of the key.
     */
    public function getFilename(): string
    {
        $lastSlash = strrpos($this->value, '/');
        
        if ($lastSlash === false) {
            return $this->value;
        }
        
        return substr($this->value, $lastSlash + 1);
    }

    /**
     * Get file extension if present.
     */
    public function getExtension(): ?string
    {
        $filename = $this->getFilename();
        $lastDot = strrpos($filename, '.');
        
        if ($lastDot === false || $lastDot === 0) {
            return null;
        }
        
        return substr($filename, $lastDot + 1);
    }

    /**
     * Validate object key according to IBM COS rules.
     */
    private function isValidObjectKey(string $key): bool
    {
        // IBM COS object key rules:
        // - 1-1024 characters
        // - UTF-8 encoded
        // - avoid characters that require URL encoding in REST API
        
        if (strlen($key) > 1024) {
            return false;
        }
        
        // Check for invalid characters that cause issues in REST APIs.
        $invalidChars = ['\\', '{', '}', '^', '`', '[', ']'];
        foreach ($invalidChars as $char) {
            if (str_contains($key, $char)) {
                return false;
            }
        }
        
        return true;
    }
}