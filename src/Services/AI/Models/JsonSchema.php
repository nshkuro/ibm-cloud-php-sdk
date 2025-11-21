<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use InvalidArgumentException;

/**
 * JSON Schema builder for structured LLM outputs.
 *
 * Provides a fluent interface for building JSON schemas that can be used
 * with ResponseFormat::jsonSchema() to enforce strict output structure.
 */
final class JsonSchema
{
    private array $schema = [];

    private function __construct(array $schema = [])
    {
        $this->schema = $schema;
    }

    /**
     * Create a new object schema.
     *
     * @param array $properties Associative array of property definitions.
     * @param array $required List of required property names.
     */
    public static function object(array $properties, array $required = []): self
    {
        return new self([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ]);
    }

    /**
     * Create a string property.
     *
     * @param string|null $description Description of the property.
     * @param array|null $enum List of allowed values.
     */
    public static function string(?string $description = null, ?array $enum = null): array
    {
        $prop = ['type' => 'string'];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        if ($enum !== null) {
            $prop['enum'] = $enum;
        }

        return $prop;
    }

    /**
     * Create an integer property.
     *
     * @param string|null $description Description of the property.
     * @param int|null $minimum Minimum value.
     * @param int|null $maximum Maximum value.
     */
    public static function integer(?string $description = null, ?int $minimum = null, ?int $maximum = null): array
    {
        $prop = ['type' => 'integer'];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        if ($minimum !== null) {
            $prop['minimum'] = $minimum;
        }

        if ($maximum !== null) {
            $prop['maximum'] = $maximum;
        }

        return $prop;
    }

    /**
     * Create a number property (float/double).
     *
     * @param string|null $description Description of the property.
     * @param float|null $minimum Minimum value.
     * @param float|null $maximum Maximum value.
     */
    public static function number(?string $description = null, ?float $minimum = null, ?float $maximum = null): array
    {
        $prop = ['type' => 'number'];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        if ($minimum !== null) {
            $prop['minimum'] = $minimum;
        }

        if ($maximum !== null) {
            $prop['maximum'] = $maximum;
        }

        return $prop;
    }

    /**
     * Create a boolean property.
     *
     * @param string|null $description Description of the property.
     */
    public static function boolean(?string $description = null): array
    {
        $prop = ['type' => 'boolean'];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        return $prop;
    }

    /**
     * Create an array property.
     *
     * @param array $items Schema for array items.
     * @param string|null $description Description of the property.
     * @param int|null $minItems Minimum number of items.
     * @param int|null $maxItems Maximum number of items.
     */
    public static function array(array $items, ?string $description = null, ?int $minItems = null, ?int $maxItems = null): array
    {
        $prop = [
            'type' => 'array',
            'items' => $items,
        ];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        if ($minItems !== null) {
            $prop['minItems'] = $minItems;
        }

        if ($maxItems !== null) {
            $prop['maxItems'] = $maxItems;
        }

        return $prop;
    }

    /**
     * Create a nested object property.
     *
     * @param array $properties Properties of the nested object.
     * @param array $required Required properties.
     * @param string|null $description Description of the property.
     */
    public static function nestedObject(array $properties, array $required = [], ?string $description = null): array
    {
        $prop = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];

        if (!empty($required)) {
            $prop['required'] = $required;
        }

        if ($description !== null) {
            $prop['description'] = $description;
        }

        return $prop;
    }

    /**
     * Create a property that can be one of multiple types.
     *
     * @param array $types Array of type definitions.
     * @param string|null $description Description of the property.
     */
    public static function oneOf(array $types, ?string $description = null): array
    {
        $prop = ['oneOf' => $types];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        return $prop;
    }

    /**
     * Create a property that can be any of multiple types.
     *
     * @param array $types Array of type definitions.
     * @param string|null $description Description of the property.
     */
    public static function anyOf(array $types, ?string $description = null): array
    {
        $prop = ['anyOf' => $types];

        if ($description !== null) {
            $prop['description'] = $description;
        }

        return $prop;
    }

    /**
     * Add additional properties to the schema.
     *
     * @param array $properties Additional properties to add.
     */
    public function withProperties(array $properties): self
    {
        $schema = $this->schema;
        $schema['properties'] = array_merge($schema['properties'] ?? [], $properties);

        return new self($schema);
    }

    /**
     * Set required properties.
     *
     * @param array $required List of required property names.
     */
    public function withRequired(array $required): self
    {
        $schema = $this->schema;
        $schema['required'] = $required;

        return new self($schema);
    }

    /**
     * Add description to the schema.
     *
     * @param string $description Schema description.
     */
    public function withDescription(string $description): self
    {
        $schema = $this->schema;
        $schema['description'] = $description;

        return new self($schema);
    }

    /**
     * Allow additional properties.
     */
    public function allowAdditionalProperties(): self
    {
        $schema = $this->schema;
        $schema['additionalProperties'] = true;

        return new self($schema);
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        if (empty($this->schema)) {
            throw new InvalidArgumentException('Schema cannot be empty.');
        }

        return $this->schema;
    }

    /**
     * Create a schema from a raw array.
     *
     * @param array $schema Raw schema definition.
     */
    public static function fromArray(array $schema): self
    {
        return new self($schema);
    }
}
