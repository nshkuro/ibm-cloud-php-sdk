<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use InvalidArgumentException;

/**
 * Response format configuration for chat completions.
 *
 * Supports different response format types including JSON object and JSON schema.
 */
final class ResponseFormat
{
    public const TYPE_JSON_OBJECT = 'json_object';
    public const TYPE_JSON_SCHEMA = 'json_schema';
    public const TYPE_TEXT = 'text';

    private function __construct(
        public readonly string $type,
        public readonly ?array $jsonSchema = null
    ) {
        if (!in_array($type, [self::TYPE_JSON_OBJECT, self::TYPE_JSON_SCHEMA, self::TYPE_TEXT])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid response format type "%s". Must be one of: %s.',
                    $type,
                    implode(', ', [self::TYPE_JSON_OBJECT, self::TYPE_JSON_SCHEMA, self::TYPE_TEXT])
                )
            );
        }

        if ($type === self::TYPE_JSON_SCHEMA && $jsonSchema === null) {
            throw new InvalidArgumentException(
                'JSON schema must be provided when using json_schema response format type.'
            );
        }

        if ($type !== self::TYPE_JSON_SCHEMA && $jsonSchema !== null) {
            throw new InvalidArgumentException(
                sprintf(
                    'JSON schema can only be provided with json_schema response format type, got "%s".',
                    $type
                )
            );
        }
    }

    /**
     * Create a JSON object response format.
     * The model will return valid JSON, but structure is not enforced.
     */
    public static function jsonObject(): self
    {
        return new self(self::TYPE_JSON_OBJECT);
    }

    /**
     * Create a JSON schema response format.
     * The model will return JSON that strictly conforms to the provided schema.
     *
     * @param string $name A descriptive name for the schema.
     * @param array $schema The JSON schema definition (must be a valid JSON schema object).
     * @param bool $strict Whether to enforce strict schema validation.
     */
    public static function jsonSchema(string $name, array $schema, bool $strict = true): self
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Schema name cannot be empty.');
        }

        if (empty($schema)) {
            throw new InvalidArgumentException('Schema definition cannot be empty.');
        }

        return new self(
            self::TYPE_JSON_SCHEMA,
            [
                'name' => $name,
                'schema' => $schema,
                'strict' => $strict,
            ]
        );
    }

    /**
     * Create a text response format (default).
     */
    public static function text(): self
    {
        return new self(self::TYPE_TEXT);
    }

    /**
     * Check if this is a JSON response format.
     */
    public function isJson(): bool
    {
        return $this->type === self::TYPE_JSON_OBJECT || $this->type === self::TYPE_JSON_SCHEMA;
    }

    /**
     * Check if this response format uses a strict schema.
     */
    public function hasStrictSchema(): bool
    {
        return $this->type === self::TYPE_JSON_SCHEMA && ($this->jsonSchema['strict'] ?? false);
    }

    /**
     * Get the schema name (only for json_schema type).
     */
    public function getSchemaName(): ?string
    {
        return $this->jsonSchema['name'] ?? null;
    }

    /**
     * Convert to array format for API request.
     */
    public function toArray(): array
    {
        $result = ['type' => $this->type];

        if ($this->type === self::TYPE_JSON_SCHEMA && $this->jsonSchema !== null) {
            $result['json_schema'] = $this->jsonSchema;
        }

        return $result;
    }

    /**
     * Create from array format (for backwards compatibility).
     *
     * @param array $data Array with 'type' and optionally 'json_schema'.
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['type'])) {
            throw new InvalidArgumentException('Response format array must contain "type" field.');
        }

        $type = $data['type'];
        $jsonSchema = $data['json_schema'] ?? null;

        return new self($type, $jsonSchema);
    }
}
