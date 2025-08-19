<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\Models;

use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\ValueObjects\Temperature;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GenerationParametersTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $params = new GenerationParameters();
        
        $this->assertSame('greedy', $params->getDecodingMethod());
        $this->assertSame(100, $params->getMaxNewTokens());
    }

    public function testSetDecodingMethod(): void
    {
        $params = (new GenerationParameters())->decodingMethod('sample');
        
        $this->assertSame('sample', $params->getDecodingMethod());
    }

    public function testRejectsInvalidDecodingMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decoding method must be either "greedy" or "sample".');
        
        (new GenerationParameters())->decodingMethod('invalid');
    }

    public function testSetMaxNewTokens(): void
    {
        $params = (new GenerationParameters())->maxNewTokens(500);
        
        $this->assertSame(500, $params->getMaxNewTokens());
    }

    public function testRejectsInvalidMaxNewTokens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max new tokens must be between 1 and 4096.');
        
        (new GenerationParameters())->maxNewTokens(5000);
    }

    public function testSetMinNewTokens(): void
    {
        $params = (new GenerationParameters())->minNewTokens(10);
        
        $this->assertSame(10, $params->getMinNewTokens());
    }

    public function testRejectsNegativeMinNewTokens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Min new tokens cannot be negative.');
        
        (new GenerationParameters())->minNewTokens(-1);
    }

    public function testSetTemperature(): void
    {
        $temp = Temperature::balanced();
        $params = (new GenerationParameters())->temperature($temp);
        
        $this->assertSame($temp, $params->getTemperature());
    }

    public function testSetTopK(): void
    {
        $params = (new GenerationParameters())->topK(50);
        
        $this->assertSame(50, $params->getTopK());
    }

    public function testRejectsInvalidTopK(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Top-K must be between 1 and 100.');
        
        (new GenerationParameters())->topK(150);
    }

    public function testSetTopP(): void
    {
        $params = (new GenerationParameters())->topP(0.9);
        
        $this->assertSame(0.9, $params->getTopP());
    }

    public function testRejectsInvalidTopP(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Top-P must be between 0.0 and 1.0.');
        
        (new GenerationParameters())->topP(1.5);
    }

    public function testSetRandomSeed(): void
    {
        $params = (new GenerationParameters())->randomSeed(42);
        
        $this->assertSame(42, $params->getRandomSeed());
    }

    public function testSetRepetitionPenalty(): void
    {
        $params = (new GenerationParameters())->repetitionPenalty(1.1);
        
        $this->assertSame(1.1, $params->getRepetitionPenalty());
    }

    public function testRejectsInvalidRepetitionPenalty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Repetition penalty must be between 1.0 and 2.0.');
        
        (new GenerationParameters())->repetitionPenalty(0.5);
    }

    public function testSetStopSequences(): void
    {
        $sequences = ['\n\n', '```'];
        $params = (new GenerationParameters())->stopSequences($sequences);
        
        $this->assertSame($sequences, $params->getStopSequences());
    }

    public function testRejectsTooManyStopSequences(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 6 stop sequences are allowed.');
        
        (new GenerationParameters())->stopSequences(['1', '2', '3', '4', '5', '6', '7']);
    }

    public function testRejectsEmptyStopSequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stop sequences must be non-empty strings.');
        
        (new GenerationParameters())->stopSequences(['valid', '']);
    }

    public function testSetTimeLimit(): void
    {
        $params = (new GenerationParameters())->timeLimit(5000);
        
        $this->assertSame(5000, $params->getTimeLimit());
    }

    public function testRejectsInvalidTimeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Time limit must be between 1,000 and 600,000 milliseconds');
        
        (new GenerationParameters())->timeLimit(500);
    }

    public function testSetTypicalP(): void
    {
        $params = (new GenerationParameters())->typicalP(0.8);
        
        $this->assertSame(0.8, $params->getTypicalP());
    }

    public function testSetPromptVariables(): void
    {
        $variables = ['name' => 'John', 'age' => 30];
        $params = (new GenerationParameters())->promptVariables($variables);
        
        $this->assertSame($variables, $params->getPromptVariables());
    }

    public function testPresetForCodeGeneration(): void
    {
        $params = GenerationParameters::forCodeGeneration();
        
        $this->assertSame('greedy', $params->getDecodingMethod());
        $this->assertTrue($params->getTemperature()->isDeterministic());
        $this->assertSame(1000, $params->getMaxNewTokens());
        $this->assertContains('\n\n', $params->getStopSequences());
    }

    public function testPresetForCreativeWriting(): void
    {
        $params = GenerationParameters::forCreativeWriting();
        
        $this->assertSame('sample', $params->getDecodingMethod());
        $this->assertTrue($params->getTemperature()->isModerate());
        $this->assertSame(0.9, $params->getTopP());
        $this->assertSame(1.1, $params->getRepetitionPenalty());
    }

    public function testPresetForQuestionAnswering(): void
    {
        $params = GenerationParameters::forQuestionAnswering();
        
        $this->assertSame('greedy', $params->getDecodingMethod());
        $this->assertTrue($params->getTemperature()->isLow());
        $this->assertSame(200, $params->getMaxNewTokens());
    }

    public function testToArray(): void
    {
        $params = (new GenerationParameters())
            ->decodingMethod('sample')
            ->maxNewTokens(500)
            ->temperature(Temperature::balanced())
            ->topK(50)
            ->randomSeed(42);
        
        $array = $params->toArray();
        
        $this->assertSame('sample', $array['decoding_method']);
        $this->assertSame(500, $array['max_new_tokens']);
        $this->assertSame(0.7, $array['temperature']);
        $this->assertSame(50, $array['top_k']);
        $this->assertSame(42, $array['random_seed']);
    }

    public function testToArrayExcludesNullValues(): void
    {
        $params = new GenerationParameters();
        $array = $params->toArray();
        
        $this->assertArrayNotHasKey('min_new_tokens', $array);
        $this->assertArrayNotHasKey('temperature', $array);
        $this->assertArrayNotHasKey('top_k', $array);
    }
}