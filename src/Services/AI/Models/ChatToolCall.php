<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use InvalidArgumentException;

final class ChatToolCall
{
    private function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly array $function
    ) {
        $this->validate();
    }

    /**
     * Create a function tool call.
     */
    public static function function(
        string $id,
        string $name,
        string $arguments
    ): self {
        return new self($id, 'function', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Create from array (for API responses).
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id'])) {
            throw new InvalidArgumentException('Tool call must have an ID.');
        }

        if (!isset($data['type'])) {
            throw new InvalidArgumentException('Tool call must have a type.');
        }

        if (!isset($data['function'])) {
            throw new InvalidArgumentException('Tool call must have a function.');
        }

        return new self($data['id'], $data['type'], $data['function']);
    }

    /**
     * Validate tool call structure.
     */
    private function validate(): void
    {
        if (empty($this->id)) {
            throw new InvalidArgumentException('Tool call ID cannot be empty.');
        }

        if ($this->type !== 'function') {
            throw new InvalidArgumentException('Only "function" type tool calls are currently supported.');
        }

        if (!isset($this->function['name'])) {
            throw new InvalidArgumentException('Function tool call must have a name.');
        }

        if (!isset($this->function['arguments'])) {
            throw new InvalidArgumentException('Function tool call must have arguments.');
        }
    }

    /**
     * Parse function arguments from JSON string.
     */
    public function parseArguments(): array
    {
        $arguments = $this->function['arguments'];

        if (empty($arguments)) {
            return [];
        }

        $parsed = json_decode($arguments, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                'Failed to parse function arguments: ' . json_last_error_msg()
            );
        }

        return $parsed;
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'function' => $this->function,
        ];
    }

    /**
     * Get the tool call ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the tool call type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the function name.
     */
    public function getFunctionName(): string
    {
        return $this->function['name'];
    }

    /**
     * Get the function arguments as string.
     */
    public function getArguments(): string
    {
        return $this->function['arguments'];
    }

    /**
     * Get the complete function data.
     */
    public function getFunction(): array
    {
        return $this->function;
    }

    /**
     * String representation.
     */
    public function toString(): string
    {
        $name = $this->getFunctionName();
        return "ToolCall({$this->id}): {$name}({$this->function['arguments']})";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}