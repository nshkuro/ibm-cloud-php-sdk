<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\Models\Requests;

use IBMCloud\Services\AI\Models\Requests\ChatRequest;
use IBMCloud\Services\AI\Models\ResponseFormat;
use IBMCloud\Services\AI\ValueObjects\ChatMessage;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChatRequestTest extends TestCase
{
    private function createBasicRequest(): ChatRequest
    {
        return ChatRequest::create(
            ModelId::from('test/model'),
            [ChatMessage::user('Hello')]
        );
    }

    public function testWithJsonSchemaFromArray(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string']
            ]
        ];

        $request = $this->createBasicRequest()
            ->withJsonSchema('TestSchema', $schema, true);

        $responseFormat = $request->getResponseFormat();

        $this->assertInstanceOf(ResponseFormat::class, $responseFormat);
        $this->assertSame(ResponseFormat::TYPE_JSON_SCHEMA, $responseFormat->type);
        $this->assertSame('TestSchema', $responseFormat->getSchemaName());
        $this->assertTrue($responseFormat->hasStrictSchema());
    }

    public function testWithJsonSchemaFromJsonString(): void
    {
        $schemaJson = json_encode([
            'type' => 'object',
            'properties' => [
                'age' => ['type' => 'integer']
            ]
        ]);

        $request = $this->createBasicRequest()
            ->withJsonSchema('AgeSchema', $schemaJson, true);

        $responseFormat = $request->getResponseFormat();

        $this->assertInstanceOf(ResponseFormat::class, $responseFormat);
        $this->assertSame(ResponseFormat::TYPE_JSON_SCHEMA, $responseFormat->type);
        $this->assertSame('AgeSchema', $responseFormat->getSchemaName());
    }

    public function testWithJsonSchemaRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON schema string');

        $this->createBasicRequest()
            ->withJsonSchema('BadSchema', '{invalid json}', true);
    }

    public function testWithJsonSchemaInToArray(): void
    {
        $schema = ['type' => 'object'];

        $request = $this->createBasicRequest()
            ->withProjectId('test-project')
            ->withJsonSchema('TestSchema', $schema, true);

        $array = $request->toArray();

        $this->assertArrayHasKey('response_format', $array);
        $this->assertSame('json_schema', $array['response_format']['type']);
        $this->assertArrayHasKey('json_schema', $array['response_format']);
        $this->assertSame('TestSchema', $array['response_format']['json_schema']['name']);
    }

    public function testWithJsonMode(): void
    {
        $request = $this->createBasicRequest()
            ->withJsonMode();

        $responseFormat = $request->getResponseFormat();

        $this->assertInstanceOf(ResponseFormat::class, $responseFormat);
        $this->assertSame(ResponseFormat::TYPE_JSON_OBJECT, $responseFormat->type);
        $this->assertTrue($responseFormat->isJson());
        $this->assertFalse($responseFormat->hasStrictSchema());
    }

    public function testBackwardsCompatibilityWithResponseFormatArray(): void
    {
        // Old way - passing array directly.
        $oldFormat = [
            'type' => 'json_object'
        ];

        $request = $this->createBasicRequest()
            ->withResponseFormat($oldFormat);

        $responseFormat = $request->getResponseFormat();

        $this->assertInstanceOf(ResponseFormat::class, $responseFormat);
        $this->assertSame(ResponseFormat::TYPE_JSON_OBJECT, $responseFormat->type);
    }

    public function testBackwardsCompatibilityWithJsonSchemaArray(): void
    {
        // Old way - passing full json_schema format as array.
        $oldFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'OldSchema',
                'schema' => ['type' => 'object'],
                'strict' => true
            ]
        ];

        $request = $this->createBasicRequest()
            ->withResponseFormat($oldFormat);

        $responseFormat = $request->getResponseFormat();

        $this->assertInstanceOf(ResponseFormat::class, $responseFormat);
        $this->assertSame(ResponseFormat::TYPE_JSON_SCHEMA, $responseFormat->type);
        $this->assertSame('OldSchema', $responseFormat->getSchemaName());
    }

    public function testWithResponseFormatAcceptsResponseFormatObject(): void
    {
        $format = ResponseFormat::jsonObject();

        $request = $this->createBasicRequest()
            ->withResponseFormat($format);

        $responseFormat = $request->getResponseFormat();

        $this->assertSame($format, $responseFormat);
    }

    public function testToArrayIncludesResponseFormat(): void
    {
        $request = $this->createBasicRequest()
            ->withProjectId('test-project')
            ->withJsonMode();

        $array = $request->toArray();

        $this->assertArrayHasKey('response_format', $array);
        $this->assertSame(['type' => 'json_object'], $array['response_format']);
    }

    public function testToArrayExcludesNullResponseFormat(): void
    {
        $request = $this->createBasicRequest()
            ->withProjectId('test-project');

        $array = $request->toArray();

        $this->assertArrayNotHasKey('response_format', $array);
    }

    public function testJsonSchemaWithNonStrictMode(): void
    {
        $schema = ['type' => 'object'];

        $request = $this->createBasicRequest()
            ->withJsonSchema('TestSchema', $schema, false);

        $responseFormat = $request->getResponseFormat();

        $this->assertFalse($responseFormat->hasStrictSchema());
    }

    public function testConstructorBackwardsCompatibilityWithArrayResponseFormat(): void
    {
        // Testing constructor accepts array for backwards compatibility.
        $request = new ChatRequest(
            ModelId::from('test/model'),
            [ChatMessage::user('Hello')],
            'test-project',
            null,
            null,
            [],
            null,
            null,
            ['type' => 'json_object'] // Old array format.
        );

        $responseFormat = $request->getResponseFormat();

        $this->assertInstanceOf(ResponseFormat::class, $responseFormat);
        $this->assertSame(ResponseFormat::TYPE_JSON_OBJECT, $responseFormat->type);
    }
}
