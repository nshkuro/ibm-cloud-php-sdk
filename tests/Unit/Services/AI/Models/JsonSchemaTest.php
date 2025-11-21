<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\Models;

use IBMCloud\Services\AI\Models\JsonSchema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JsonSchemaTest extends TestCase
{
    public function testCreateObjectSchema(): void
    {
        $schema = JsonSchema::object(
            [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer']
            ],
            ['name', 'age']
        );

        $array = $schema->toArray();

        $this->assertSame('object', $array['type']);
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
        $this->assertSame(['name', 'age'], $array['required']);
        $this->assertFalse($array['additionalProperties']);
    }

    public function testStringProperty(): void
    {
        $prop = JsonSchema::string('A string value');

        $this->assertSame('string', $prop['type']);
        $this->assertSame('A string value', $prop['description']);
    }

    public function testStringPropertyWithEnum(): void
    {
        $prop = JsonSchema::string('Status', ['active', 'inactive', 'pending']);

        $this->assertSame('string', $prop['type']);
        $this->assertSame(['active', 'inactive', 'pending'], $prop['enum']);
    }

    public function testIntegerProperty(): void
    {
        $prop = JsonSchema::integer('Age', 0, 120);

        $this->assertSame('integer', $prop['type']);
        $this->assertSame('Age', $prop['description']);
        $this->assertSame(0, $prop['minimum']);
        $this->assertSame(120, $prop['maximum']);
    }

    public function testIntegerPropertyWithoutConstraints(): void
    {
        $prop = JsonSchema::integer();

        $this->assertSame('integer', $prop['type']);
        $this->assertArrayNotHasKey('description', $prop);
        $this->assertArrayNotHasKey('minimum', $prop);
        $this->assertArrayNotHasKey('maximum', $prop);
    }

    public function testNumberProperty(): void
    {
        $prop = JsonSchema::number('Score', 0.0, 1.0);

        $this->assertSame('number', $prop['type']);
        $this->assertSame('Score', $prop['description']);
        $this->assertSame(0.0, $prop['minimum']);
        $this->assertSame(1.0, $prop['maximum']);
    }

    public function testBooleanProperty(): void
    {
        $prop = JsonSchema::boolean('Is active');

        $this->assertSame('boolean', $prop['type']);
        $this->assertSame('Is active', $prop['description']);
    }

    public function testArrayProperty(): void
    {
        $prop = JsonSchema::array(
            ['type' => 'string'],
            'List of tags',
            1,
            10
        );

        $this->assertSame('array', $prop['type']);
        $this->assertSame(['type' => 'string'], $prop['items']);
        $this->assertSame('List of tags', $prop['description']);
        $this->assertSame(1, $prop['minItems']);
        $this->assertSame(10, $prop['maxItems']);
    }

    public function testArrayPropertyWithoutConstraints(): void
    {
        $prop = JsonSchema::array(['type' => 'integer']);

        $this->assertSame('array', $prop['type']);
        $this->assertSame(['type' => 'integer'], $prop['items']);
        $this->assertArrayNotHasKey('minItems', $prop);
        $this->assertArrayNotHasKey('maxItems', $prop);
    }

    public function testNestedObjectProperty(): void
    {
        $prop = JsonSchema::nestedObject(
            [
                'street' => ['type' => 'string'],
                'city' => ['type' => 'string']
            ],
            ['street', 'city'],
            'Address information'
        );

        $this->assertSame('object', $prop['type']);
        $this->assertArrayHasKey('properties', $prop);
        $this->assertSame(['street', 'city'], $prop['required']);
        $this->assertSame('Address information', $prop['description']);
        $this->assertFalse($prop['additionalProperties']);
    }

    public function testOneOfProperty(): void
    {
        $prop = JsonSchema::oneOf(
            [
                ['type' => 'string'],
                ['type' => 'number']
            ],
            'String or number'
        );

        $this->assertArrayHasKey('oneOf', $prop);
        $this->assertCount(2, $prop['oneOf']);
        $this->assertSame('String or number', $prop['description']);
    }

    public function testAnyOfProperty(): void
    {
        $prop = JsonSchema::anyOf(
            [
                ['type' => 'string'],
                ['type' => 'null']
            ],
            'Optional string'
        );

        $this->assertArrayHasKey('anyOf', $prop);
        $this->assertCount(2, $prop['anyOf']);
        $this->assertSame('Optional string', $prop['description']);
    }

    public function testWithProperties(): void
    {
        $schema = JsonSchema::object(
            ['name' => ['type' => 'string']],
            ['name']
        );

        $updated = $schema->withProperties([
            'email' => ['type' => 'string'],
            'age' => ['type' => 'integer']
        ]);

        $array = $updated->toArray();

        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('email', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
    }

    public function testWithRequired(): void
    {
        $schema = JsonSchema::object(
            ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']],
            ['name']
        );

        $updated = $schema->withRequired(['name', 'age']);

        $array = $updated->toArray();

        $this->assertSame(['name', 'age'], $array['required']);
    }

    public function testWithDescription(): void
    {
        $schema = JsonSchema::object(
            ['name' => ['type' => 'string']],
            ['name']
        );

        $updated = $schema->withDescription('User information');

        $array = $updated->toArray();

        $this->assertSame('User information', $array['description']);
    }

    public function testAllowAdditionalProperties(): void
    {
        $schema = JsonSchema::object(
            ['name' => ['type' => 'string']],
            ['name']
        );

        $array = $schema->toArray();
        $this->assertFalse($array['additionalProperties']);

        $updated = $schema->allowAdditionalProperties();
        $arrayUpdated = $updated->toArray();

        $this->assertTrue($arrayUpdated['additionalProperties']);
    }

    public function testFromArray(): void
    {
        $data = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string']
            ],
            'required' => ['name']
        ];

        $schema = JsonSchema::fromArray($data);
        $array = $schema->toArray();

        $this->assertSame($data, $array);
    }

    public function testToArrayRejectsEmptySchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema cannot be empty');

        $schema = JsonSchema::fromArray([]);
        $schema->toArray();
    }

    public function testComplexNestedSchema(): void
    {
        $schema = JsonSchema::object(
            [
                'user' => JsonSchema::nestedObject(
                    [
                        'name' => JsonSchema::string('User name'),
                        'age' => JsonSchema::integer('User age', 0, 120),
                        'email' => JsonSchema::string('Email address')
                    ],
                    ['name', 'email']
                ),
                'tags' => JsonSchema::array(
                    ['type' => 'string'],
                    'Tags list',
                    0,
                    10
                ),
                'status' => JsonSchema::string('Status', ['active', 'inactive'])
            ],
            ['user', 'status']
        );

        $array = $schema->toArray();

        $this->assertSame('object', $array['type']);
        $this->assertArrayHasKey('user', $array['properties']);
        $this->assertArrayHasKey('tags', $array['properties']);
        $this->assertArrayHasKey('status', $array['properties']);
        $this->assertSame(['user', 'status'], $array['required']);

        // Check nested user object.
        $this->assertSame('object', $array['properties']['user']['type']);
        $this->assertArrayHasKey('name', $array['properties']['user']['properties']);
        $this->assertSame(['name', 'email'], $array['properties']['user']['required']);

        // Check tags array.
        $this->assertSame('array', $array['properties']['tags']['type']);
        $this->assertSame(['type' => 'string'], $array['properties']['tags']['items']);

        // Check status enum.
        $this->assertSame(['active', 'inactive'], $array['properties']['status']['enum']);
    }

    public function testImmutability(): void
    {
        $original = JsonSchema::object(
            ['name' => ['type' => 'string']],
            ['name']
        );

        $modified = $original->withDescription('Modified');

        $originalArray = $original->toArray();
        $modifiedArray = $modified->toArray();

        // Original should not have description.
        $this->assertArrayNotHasKey('description', $originalArray);

        // Modified should have description.
        $this->assertSame('Modified', $modifiedArray['description']);
    }
}
