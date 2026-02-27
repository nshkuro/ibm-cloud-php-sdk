<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\ValueObjects;

use IBMCloud\Services\AI\ValueObjects\ChatContent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChatContentTest extends TestCase
{
    // -- fromText --

    public function testFromTextCreatesTextContent(): void
    {
        $content = ChatContent::fromText('Hello');

        $this->assertTrue($content->isText());
        $this->assertFalse($content->isStructured());
        $this->assertSame('Hello', $content->getText());
        $this->assertSame('Hello', $content->toArray());
    }

    public function testFromTextAllowsEmptyString(): void
    {
        $content = ChatContent::fromText('');

        $this->assertSame('', $content->getText());
    }

    // -- fromArray with single structured content --

    public function testFromArrayWithTextType(): void
    {
        $content = ChatContent::fromArray([
            'type' => 'text',
            'text' => 'Hello world',
        ]);

        $this->assertTrue($content->isStructured());
        $this->assertSame('Hello world', $content->getText());
    }

    public function testFromArrayWithImageUrlType(): void
    {
        $input = [
            'type' => 'image_url',
            'image_url' => ['url' => 'https://example.com/img.png'],
        ];

        $content = ChatContent::fromArray($input);

        $this->assertTrue($content->isStructured());
        $this->assertSame($input, $content->toArray());
    }

    public function testFromArrayRejectsEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chat content array cannot be empty.');

        ChatContent::fromArray([]);
    }

    public function testFromArrayRejectsTextTypeWithoutTextField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ChatContent::fromArray(['type' => 'text']);
    }

    public function testFromArrayRejectsAssociativeArrayWithoutType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured content must have a "type" field.');

        ChatContent::fromArray(['foo' => 'bar']);
    }

    // -- fromArray with indexed array (multimodal) --

    public function testFromArrayWithIndexedArrayOfParts(): void
    {
        $content = ChatContent::fromArray([
            ['type' => 'text', 'text' => 'Describe this image'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/img.png']],
        ]);

        $this->assertTrue($content->isStructured());

        $array = $content->toArray();
        $this->assertCount(2, $array);
        $this->assertSame('text', $array[0]['type']);
        $this->assertSame('image_url', $array[1]['type']);
    }

    public function testFromArrayWithIndexedArrayOfStrings(): void
    {
        $content = ChatContent::fromArray([
            'First part',
            'Second part',
        ]);

        $array = $content->toArray();
        $this->assertCount(2, $array);
        $this->assertSame('text', $array[0]['type']);
        $this->assertSame('First part', $array[0]['text']);
        $this->assertSame('text', $array[1]['type']);
        $this->assertSame('Second part', $array[1]['text']);
    }

    public function testFromArrayWithMixedStringAndStructuredParts(): void
    {
        $content = ChatContent::fromArray([
            'Describe this:',
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/img.png']],
        ]);

        $array = $content->toArray();
        $this->assertCount(2, $array);
        $this->assertSame('text', $array[0]['type']);
        $this->assertSame('Describe this:', $array[0]['text']);
        $this->assertSame('image_url', $array[1]['type']);
    }

    public function testFromArrayWithSingleElementIndexedArray(): void
    {
        $content = ChatContent::fromArray([
            ['type' => 'text', 'text' => 'Only part'],
        ]);

        $array = $content->toArray();
        $this->assertCount(1, $array);
        $this->assertSame('Only part', $array[0]['text']);
    }

    // -- fromParts --

    public function testFromPartsRejectsEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Content parts cannot be empty.');

        ChatContent::fromParts([]);
    }

    public function testFromPartsRejectsPartWithoutType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each content part must have a "type" field.');

        ChatContent::fromParts([
            ['foo' => 'bar'],
        ]);
    }

    public function testFromPartsRejectsInvalidPartType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Content parts must be strings or arrays.');

        ChatContent::fromParts([123]);
    }

    // -- toString --

    public function testToStringForText(): void
    {
        $content = ChatContent::fromText('Hello');

        $this->assertSame('Hello', $content->toString());
        $this->assertSame('Hello', (string) $content);
    }

    public function testToStringForStructuredText(): void
    {
        $content = ChatContent::fromArray(['type' => 'text', 'text' => 'Structured']);

        $this->assertSame('Structured', $content->toString());
    }

    public function testToStringConcatenatesTextPartsFromMultimodal(): void
    {
        $content = ChatContent::fromArray([
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/img.png']],
            ['type' => 'text', 'text' => 'world'],
        ]);

        $this->assertSame('Hello world', $content->toString());
    }

    // -- gettext returns null for non-text --

    public function testGetTextReturnsNullForMultimodalContent(): void
    {
        $content = ChatContent::fromArray([
            ['type' => 'text', 'text' => 'Hi'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/img.png']],
        ]);

        $this->assertNull($content->getText());
    }

    public function testGetTextReturnsNullForImageContent(): void
    {
        $content = ChatContent::fromArray([
            'type' => 'image_url',
            'image_url' => ['url' => 'https://example.com/img.png'],
        ]);

        $this->assertNull($content->getText());
    }
}
