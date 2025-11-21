<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\Models;

use IBMCloud\Services\AI\Models\ResponseFormat;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseFormatTest extends TestCase
{
    public function testCreateJsonObjectFormat(): void
    {
        $format = ResponseFormat::jsonObject();

        $this->assertSame(ResponseFormat::TYPE_JSON_OBJECT, $format->type);
        $this->assertNull($format->jsonSchema);
        $this->assertTrue($format->isJson());
        $this->assertFalse($format->hasStrictSchema());
    }

    public function testCreateTextFormat(): void
    {
        $format = ResponseFormat::text();

        $this->assertSame(ResponseFormat::TYPE_TEXT, $format->type);
        $this->assertNull($format->jsonSchema);
        $this->assertFalse($format->isJson());
        $this->assertFalse($format->hasStrictSchema());
    }

    public function testCreateJsonSchemaFormat(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string']
            ]
        ];

        $format = ResponseFormat::jsonSchema('TestSchema', $schema, true);

        $this->assertSame(ResponseFormat::TYPE_JSON_SCHEMA, $format->type);
        $this->assertNotNull($format->jsonSchema);
        $this->assertSame('TestSchema', $format->jsonSchema['name']);
        $this->assertSame($schema, $format->jsonSchema['schema']);
        $this->assertTrue($format->jsonSchema['strict']);
        $this->assertTrue($format->isJson());
        $this->assertTrue($format->hasStrictSchema());
        $this->assertSame('TestSchema', $format->getSchemaName());
    }

    public function testJsonSchemaWithNonStrictMode(): void
    {
        $schema = ['type' => 'object'];
        $format = ResponseFormat::jsonSchema('TestSchema', $schema, false);

        $this->assertFalse($format->jsonSchema['strict']);
        $this->assertFalse($format->hasStrictSchema());
    }

    public function testRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response format type "invalid"');

        ResponseFormat::fromArray(['type' => 'invalid']);
    }

    public function testRejectsJsonSchemaWithoutSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON schema must be provided when using json_schema response format type');

        ResponseFormat::fromArray(['type' => 'json_schema']);
    }

    public function testRejectsJsonSchemaForNonJsonSchemaType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON schema can only be provided with json_schema response format type');

        ResponseFormat::fromArray([
            'type' => 'json_object',
            'json_schema' => ['some' => 'schema']
        ]);
    }

    public function testRejectsEmptySchemaName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema name cannot be empty');

        ResponseFormat::jsonSchema('', ['type' => 'object']);
    }

    public function testRejectsEmptySchemaDefinition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema definition cannot be empty');

        ResponseFormat::jsonSchema('TestSchema', []);
    }

    public function testToArrayForJsonObject(): void
    {
        $format = ResponseFormat::jsonObject();
        $array = $format->toArray();

        $this->assertSame(['type' => 'json_object'], $array);
    }

    public function testToArrayForText(): void
    {
        $format = ResponseFormat::text();
        $array = $format->toArray();

        $this->assertSame(['type' => 'text'], $array);
    }

    public function testToArrayForJsonSchema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string']
            ]
        ];

        $format = ResponseFormat::jsonSchema('TestSchema', $schema, true);
        $array = $format->toArray();

        $this->assertSame('json_schema', $array['type']);
        $this->assertArrayHasKey('json_schema', $array);
        $this->assertSame('TestSchema', $array['json_schema']['name']);
        $this->assertSame($schema, $array['json_schema']['schema']);
        $this->assertTrue($array['json_schema']['strict']);
    }

    public function testFromArrayWithJsonObject(): void
    {
        $data = ['type' => 'json_object'];
        $format = ResponseFormat::fromArray($data);

        $this->assertSame(ResponseFormat::TYPE_JSON_OBJECT, $format->type);
        $this->assertNull($format->jsonSchema);
    }

    public function testFromArrayWithText(): void
    {
        $data = ['type' => 'text'];
        $format = ResponseFormat::fromArray($data);

        $this->assertSame(ResponseFormat::TYPE_TEXT, $format->type);
        $this->assertNull($format->jsonSchema);
    }

    public function testFromArrayWithJsonSchema(): void
    {
        $schema = [
            'name' => 'TestSchema',
            'schema' => ['type' => 'object'],
            'strict' => true
        ];

        $data = [
            'type' => 'json_schema',
            'json_schema' => $schema
        ];

        $format = ResponseFormat::fromArray($data);

        $this->assertSame(ResponseFormat::TYPE_JSON_SCHEMA, $format->type);
        $this->assertSame($schema, $format->jsonSchema);
    }

    public function testFromArrayRejectsMissingType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response format array must contain "type" field');

        ResponseFormat::fromArray(['foo' => 'bar']);
    }

    public function testGetSchemaNameReturnsNullForNonJsonSchema(): void
    {
        $format = ResponseFormat::jsonObject();

        $this->assertNull($format->getSchemaName());
    }

    public function testBackwardsCompatibilityRoundTrip(): void
    {
        // Test that old array format can be converted and back.
        $oldFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'Schema',
                'schema' => ['type' => 'object'],
                'strict' => true
            ]
        ];

        $format = ResponseFormat::fromArray($oldFormat);
        $backToArray = $format->toArray();

        $this->assertSame($oldFormat, $backToArray);
    }
}
