<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\WatsonX;

use IBMCloud\Services\AI\WatsonX\CompletionRequestBuilder;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;
use IBMCloud\Services\AI\ValueObjects\Temperature;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CompletionRequestBuilderTest extends TestCase
{
    public function testCreateBuilder(): void
    {
        $builder = CompletionRequestBuilder::create();
        
        $this->assertInstanceOf(CompletionRequestBuilder::class, $builder);
    }

    public function testFluentInterface(): void
    {
        $builder = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->userMessage('Hello')
            ->projectId('test-project')
            ->creative()
            ->maxTokens(200);
        
        $request = $builder->build();
        
        $this->assertSame('ibm/granite-3b-code-instruct', $request->getModelId()->toString());
        $this->assertSame('User: Hello', $request->getPrompt()->toString());
        $this->assertSame('test-project', $request->getProjectId());
        $this->assertTrue($request->getParameters()->getTemperature()->isModerate());
        $this->assertSame(200, $request->getParameters()->getMaxNewTokens());
    }

    public function testModelSelection(): void
    {
        // Test granite8BCode selection
        $request1 = CompletionRequestBuilder::create()
            ->granite8BCode()
            ->promptText('test')
            ->projectId('test')
            ->build();
        $this->assertSame('ibm/granite-8b-code-instruct', $request1->getModelId()->toString());
        
        // Test llama3_70B selection
        $request2 = CompletionRequestBuilder::create()
            ->llama3_70B()
            ->promptText('test')
            ->projectId('test')
            ->build();
        $this->assertSame('meta-llama/llama-3-70b-instruct', $request2->getModelId()->toString());
    }

    public function testPromptVariations(): void
    {
        $builder = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->projectId('test');
        
        // Test different prompt types
        $this->assertSame('Hello', $builder->promptText('Hello')->build()->getPrompt()->toString());
        $this->assertSame('System: Help', $builder->systemMessage('Help')->build()->getPrompt()->toString());
        $this->assertSame('User: Question', $builder->userMessage('Question')->build()->getPrompt()->toString());
    }

    public function testChatPrompt(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hi'],
        ];
        
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->chat($messages)
            ->projectId('test')
            ->build();
        
        $expected = "System: You are helpful\n\nUser: Hi";
        $this->assertSame($expected, $request->getPrompt()->toString());
    }

    public function testContextPrompt(): void
    {
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->withContext('PHP is a language', 'What is it?')
            ->projectId('test')
            ->build();
        
        $expected = "Context: PHP is a language\n\nQuestion: What is it?\n\nAnswer:";
        $this->assertSame($expected, $request->getPrompt()->toString());
    }

    public function testFewShotPrompt(): void
    {
        $examples = [
            ['input' => '2+2', 'output' => '4'],
        ];
        
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->fewShot('Math', $examples, '3+3')
            ->projectId('test')
            ->build();
        
        $this->assertStringContainsString('Task: Math', $request->getPrompt()->toString());
        $this->assertStringContainsString('Input: 2+2', $request->getPrompt()->toString());
        $this->assertStringContainsString('Input: 3+3', $request->getPrompt()->toString());
    }

    public function testTemperatureSettings(): void
    {
        $builder = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->projectId('test');
        
        $this->assertTrue($builder->precise()->build()->getParameters()->getTemperature()->isDeterministic());
        $this->assertTrue($builder->focused()->build()->getParameters()->getTemperature()->isLow());
        $this->assertTrue($builder->balanced()->build()->getParameters()->getTemperature()->isModerate());
        $this->assertTrue($builder->creative()->build()->getParameters()->getTemperature()->isModerate());
    }

    public function testParameterSettings(): void
    {
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->projectId('test')
            ->maxTokens(500)
            ->minTokens(10)
            ->topK(50)
            ->topP(0.9)
            ->seed(42)
            ->repetitionPenalty(1.1)
            ->stopSequences(['END'])
            ->timeLimit(5000)
            ->build();
        
        $params = $request->getParameters();
        
        $this->assertSame(500, $params->getMaxNewTokens());
        $this->assertSame(10, $params->getMinNewTokens());
        $this->assertSame(50, $params->getTopK());
        $this->assertSame(0.9, $params->getTopP());
        $this->assertSame(42, $params->getRandomSeed());
        $this->assertSame(1.1, $params->getRepetitionPenalty());
        $this->assertSame(['END'], $params->getStopSequences());
        $this->assertSame(5000, $params->getTimeLimit());
    }

    public function testModerationSettings(): void
    {
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->projectId('test')
            ->withHAPDetection(true, 0.7)
            ->withPIIDetection(true, false)
            ->build();
        
        $moderations = $request->getModerations();
        
        $this->assertTrue($moderations['hap']['output']['enabled']);
        $this->assertSame(0.7, $moderations['hap']['output']['threshold']);
        $this->assertTrue($moderations['pii']['output']['enabled']);
        $this->assertFalse($moderations['pii']['mask']['remove_entity_value']);
    }

    public function testMetadata(): void
    {
        $metadata = ['version' => '1.0', 'source' => 'test'];
        
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->projectId('test')
            ->withMetadata($metadata)
            ->build();
        
        $this->assertSame($metadata, $request->getMetadata());
    }

    public function testPresetConfigurations(): void
    {
        $builder = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->projectId('test');
        
        // Test code generation preset
        $codeRequest = $builder->forCodeGeneration()->build();
        $this->assertTrue($codeRequest->getParameters()->getTemperature()->isDeterministic());
        $this->assertSame(1000, $codeRequest->getParameters()->getMaxNewTokens());
        
        // Test creative writing preset
        $creativeRequest = $builder->forCreativeWriting()->build();
        $this->assertTrue($creativeRequest->getParameters()->getTemperature()->isModerate());
        $this->assertSame(0.9, $creativeRequest->getParameters()->getTopP());
    }

    public function testValidationRequiresModel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model ID must be specified.');
        
        CompletionRequestBuilder::create()
            ->promptText('test')
            ->projectId('test')
            ->build();
    }

    public function testValidationRequiresPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt must be specified.');
        
        CompletionRequestBuilder::create()
            ->granite3BCode()
            ->projectId('test')
            ->build();
    }

    public function testValidationRequiresProjectOrSpace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either project ID or space ID must be specified.');
        
        CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->build();
    }

    public function testValidationRejectsBothProjectAndSpace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both project ID and space ID.');
        
        CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->projectId('project')
            ->spaceId('space')
            ->build();
    }

    public function testBuildWithSpaceId(): void
    {
        $request = CompletionRequestBuilder::create()
            ->granite3BCode()
            ->promptText('test')
            ->spaceId('test-space')
            ->build();
        
        $this->assertSame('test-space', $request->getSpaceId());
        $this->assertNull($request->getProjectId());
    }
}