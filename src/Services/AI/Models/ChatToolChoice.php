<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use InvalidArgumentException;

final class ChatToolChoice
{
    private function __construct(
        private readonly string|array|null $choice
    ) {
        $this->validate();
    }

    /**
     * Create an "auto" choice - model decides whether to call tools.
     */
    public static function auto(): self
    {
        return new self('auto');
    }

    /**
     * Create a "none" choice - model will not call any tools.
     */
    public static function none(): self
    {
        return new self('none');
    }

    /**
     * Create a "required" choice - model must call at least one tool.
     */
    public static function required(): self
    {
        return new self('required');
    }

    /**
     * Force the model to call a specific function.
     */
    public static function specific(string $functionName): self
    {
        return new self([
            'type' => 'function',
            'function' => [
                'name' => $functionName,
            ],
        ]);
    }

    /**
     * Create from array (for API responses).
     */
    public static function fromArray(string|array $data): self
    {
        return new self($data);
    }

    /**
     * Validate the tool choice.
     */
    private function validate(): void
    {
        if ($this->choice === null) {
            return; // null is valid (use model default).
        }

        if (is_string($this->choice)) {
            if (!in_array($this->choice, ['auto', 'none', 'required'], true)) {
                throw new InvalidArgumentException(
                    'Tool choice string must be "auto", "none", or "required".'
                );
            }
            return;
        }

        if (is_array($this->choice)) {
            if (!isset($this->choice['type'])) {
                throw new InvalidArgumentException('Tool choice object must have a "type" field.');
            }

            if ($this->choice['type'] !== 'function') {
                throw new InvalidArgumentException('Tool choice type must be "function".');
            }

            if (!isset($this->choice['function']['name'])) {
                throw new InvalidArgumentException('Function tool choice must specify a function name.');
            }
        }
    }

    /**
     * Check if this is an auto choice.
     */
    public function isAuto(): bool
    {
        return $this->choice === 'auto';
    }

    /**
     * Check if this is a none choice.
     */
    public function isNone(): bool
    {
        return $this->choice === 'none';
    }

    /**
     * Check if this is a required choice.
     */
    public function isRequired(): bool
    {
        return $this->choice === 'required';
    }

    /**
     * Check if this forces a specific function.
     */
    public function isSpecific(): bool
    {
        return is_array($this->choice);
    }

    /**
     * Get the specific function name (if applicable).
     */
    public function getFunctionName(): ?string
    {
        if (is_array($this->choice) && isset($this->choice['function']['name'])) {
            return $this->choice['function']['name'];
        }
        return null;
    }

    /**
     * Convert to value for API request.
     */
    public function toValue(): string|array|null
    {
        return $this->choice;
    }

    /**
     * String representation.
     */
    public function toString(): string
    {
        if ($this->choice === null) {
            return 'default';
        }

        if (is_string($this->choice)) {
            return $this->choice;
        }

        $functionName = $this->getFunctionName();
        return "specific:$functionName";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}