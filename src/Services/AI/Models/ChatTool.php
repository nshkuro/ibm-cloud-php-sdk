<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use InvalidArgumentException;

final class ChatTool
{
    private function __construct(
        private readonly string $type,
        private readonly array $function
    ) {
        $this->validate();
    }

    /**
     * Create a function tool.
     */
    public static function function(
        string $name,
        string $description,
        array $parameters = []
    ): self {
        $function = [
            'name' => $name,
            'description' => $description,
        ];

        if (!empty($parameters)) {
            $function['parameters'] = $parameters;
        }

        return new self('function', $function);
    }

    /**
     * Create from array (for API responses).
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type'])) {
            throw new InvalidArgumentException('Tool must have a type.');
        }

        if (!isset($data['function'])) {
            throw new InvalidArgumentException('Tool must have a function definition.');
        }

        return new self($data['type'], $data['function']);
    }

    /**
     * Validate tool structure.
     */
    private function validate(): void
    {
        if ($this->type !== 'function') {
            throw new InvalidArgumentException('Only "function" type tools are currently supported.');
        }

        if (!isset($this->function['name'])) {
            throw new InvalidArgumentException('Function tool must have a name.');
        }

        if (!is_string($this->function['name']) || empty($this->function['name'])) {
            throw new InvalidArgumentException('Function name must be a non-empty string.');
        }

        if (!isset($this->function['description'])) {
            throw new InvalidArgumentException('Function tool must have a description.');
        }

        if (!is_string($this->function['description']) || empty($this->function['description'])) {
            throw new InvalidArgumentException('Function description must be a non-empty string.');
        }

        // Validate parameters if present.
        if (isset($this->function['parameters'])) {
            if (!is_array($this->function['parameters'])) {
                throw new InvalidArgumentException('Function parameters must be an array.');
            }

            // Should be JSON Schema format.
            if (!isset($this->function['parameters']['type'])) {
                throw new InvalidArgumentException('Function parameters must have a "type" field (usually "object").');
            }
        }
    }

    /**
     * Create a simple parameter schema.
     */
    public static function createParameterSchema(
        array $properties,
        array $required = []
    ): array {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Helper to create a string parameter.
     */
    public static function stringParam(
        string $description,
        ?array $enumValues = null
    ): array {
        $param = [
            'type' => 'string',
            'description' => $description,
        ];

        if ($enumValues !== null) {
            $param['enum'] = $enumValues;
        }

        return $param;
    }

    /**
     * Helper to create a number parameter.
     */
    public static function numberParam(
        string $description,
        ?float $minimum = null,
        ?float $maximum = null
    ): array {
        $param = [
            'type' => 'number',
            'description' => $description,
        ];

        if ($minimum !== null) {
            $param['minimum'] = $minimum;
        }

        if ($maximum !== null) {
            $param['maximum'] = $maximum;
        }

        return $param;
    }

    /**
     * Helper to create a boolean parameter.
     */
    public static function booleanParam(string $description): array
    {
        return [
            'type' => 'boolean',
            'description' => $description,
        ];
    }

    /**
     * Helper to create an array parameter.
     */
    public static function arrayParam(
        string $description,
        array $items
    ): array {
        return [
            'type' => 'array',
            'description' => $description,
            'items' => $items,
        ];
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'function' => $this->function,
        ];
    }

    /**
     * Get the tool type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the function name.
     */
    public function getName(): string
    {
        return $this->function['name'];
    }

    /**
     * Get the function description.
     */
    public function getDescription(): string
    {
        return $this->function['description'];
    }

    /**
     * Get the function parameters.
     */
    public function getParameters(): ?array
    {
        return $this->function['parameters'] ?? null;
    }

    /**
     * Get the complete function definition.
     */
    public function getFunction(): array
    {
        return $this->function;
    }
}