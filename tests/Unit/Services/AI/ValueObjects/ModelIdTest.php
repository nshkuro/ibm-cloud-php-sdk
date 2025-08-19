<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\ValueObjects;

use IBMCloud\Services\AI\ValueObjects\ModelId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ModelIdTest extends TestCase
{
    public function testCreateValidModelId(): void
    {
        $modelId = ModelId::from('ibm/granite-3b-code-instruct');
        
        $this->assertSame('ibm/granite-3b-code-instruct', $modelId->toString());
        $this->assertSame('ibm/granite-3b-code-instruct', (string) $modelId);
    }

    public function testRejectsEmptyModelId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model ID cannot be empty.');
        
        ModelId::from('');
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model ID must be in format "provider/model-name"');
        
        ModelId::from('invalid-format');
    }

    public function testStaticFactoryMethods(): void
    {
        $this->assertSame('ibm/granite-3b-code-instruct', ModelId::granite3BCode()->toString());
        $this->assertSame('ibm/granite-8b-code-instruct', ModelId::granite8BCode()->toString());
        $this->assertSame('meta-llama/llama-3-8b-instruct', ModelId::llama3_8B()->toString());
        $this->assertSame('mistralai/mistral-7b-instruct-v0-3', ModelId::mistral7B()->toString());
    }

    public function testGetProvider(): void
    {
        $modelId = ModelId::from('ibm/granite-3b-code-instruct');
        
        $this->assertSame('ibm', $modelId->getProvider());
    }

    public function testGetModelName(): void
    {
        $modelId = ModelId::from('ibm/granite-3b-code-instruct');
        
        $this->assertSame('granite-3b-code-instruct', $modelId->getModelName());
    }

    public function testIsGranite(): void
    {
        $this->assertTrue(ModelId::granite3BCode()->isGranite());
        $this->assertFalse(ModelId::llama3_8B()->isGranite());
    }

    public function testIsCodeModel(): void
    {
        $this->assertTrue(ModelId::granite3BCode()->isCodeModel());
        $this->assertFalse(ModelId::flanUL2()->isCodeModel());
    }

    public function testIsInstructModel(): void
    {
        $this->assertTrue(ModelId::granite3BCode()->isInstructModel());
        $this->assertTrue(ModelId::llama3_8B()->isInstructModel());
        $this->assertFalse(ModelId::flanUL2()->isInstructModel());
    }
}