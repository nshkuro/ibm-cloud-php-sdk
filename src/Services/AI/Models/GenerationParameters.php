<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use IBMCloud\Services\AI\ValueObjects\Temperature;
use InvalidArgumentException;

final class GenerationParameters
{
    private ?string $decodingMethod = null;
    private ?int $maxNewTokens = null;
    private ?int $minNewTokens = null;
    private ?Temperature $temperature = null;
    private ?int $topK = null;
    private ?float $topP = null;
    private ?int $randomSeed = null;
    private ?float $repetitionPenalty = null;
    private ?array $stopSequences = null;
    private ?int $timeLimit = null;
    private ?float $typicalP = null;
    private ?array $promptVariables = null;

    public function __construct()
    {
        // Default values
        $this->decodingMethod = 'greedy';
        $this->maxNewTokens = 100;
    }

    /**
     * Set decoding method.
     */
    public function decodingMethod(string $method): self
    {
        if (!in_array($method, ['greedy', 'sample'], true)) {
            throw new InvalidArgumentException(
                'Decoding method must be either "greedy" or "sample".'
            );
        }

        $this->decodingMethod = $method;
        return $this;
    }

    /**
     * Set maximum number of new tokens to generate.
     */
    public function maxNewTokens(int $tokens): self
    {
        if ($tokens < 1 || $tokens > 4096) {
            throw new InvalidArgumentException(
                'Max new tokens must be between 1 and 4096.'
            );
        }

        $this->maxNewTokens = $tokens;
        return $this;
    }

    /**
     * Set minimum number of new tokens to generate.
     */
    public function minNewTokens(int $tokens): self
    {
        if ($tokens < 0) {
            throw new InvalidArgumentException(
                'Min new tokens cannot be negative.'
            );
        }

        $this->minNewTokens = $tokens;
        return $this;
    }

    /**
     * Set temperature for randomness in generation.
     */
    public function temperature(Temperature $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * Set top-K sampling parameter.
     */
    public function topK(int $k): self
    {
        if ($k < 1 || $k > 100) {
            throw new InvalidArgumentException(
                'Top-K must be between 1 and 100.'
            );
        }

        $this->topK = $k;
        return $this;
    }

    /**
     * Set top-P (nucleus) sampling parameter.
     */
    public function topP(float $p): self
    {
        if ($p < 0.0 || $p > 1.0) {
            throw new InvalidArgumentException(
                'Top-P must be between 0.0 and 1.0.'
            );
        }

        $this->topP = $p;
        return $this;
    }

    /**
     * Set random seed for reproducible outputs.
     */
    public function randomSeed(int $seed): self
    {
        $this->randomSeed = $seed;
        return $this;
    }

    /**
     * Set repetition penalty to avoid repetitive outputs.
     */
    public function repetitionPenalty(float $penalty): self
    {
        if ($penalty < 1.0 || $penalty > 2.0) {
            throw new InvalidArgumentException(
                'Repetition penalty must be between 1.0 and 2.0.'
            );
        }

        $this->repetitionPenalty = $penalty;
        return $this;
    }

    /**
     * Set stop sequences to end generation.
     */
    public function stopSequences(array $sequences): self
    {
        if (count($sequences) > 6) {
            throw new InvalidArgumentException(
                'Maximum 6 stop sequences are allowed.'
            );
        }

        foreach ($sequences as $sequence) {
            if (!is_string($sequence) || empty($sequence)) {
                throw new InvalidArgumentException(
                    'Stop sequences must be non-empty strings.'
                );
            }
        }

        $this->stopSequences = $sequences;
        return $this;
    }

    /**
     * Set time limit for generation in milliseconds.
     */
    public function timeLimit(int $milliseconds): self
    {
        if ($milliseconds < 1000 || $milliseconds > 600000) {
            throw new InvalidArgumentException(
                'Time limit must be between 1,000 and 600,000 milliseconds (1s - 10min).'
            );
        }

        $this->timeLimit = $milliseconds;
        return $this;
    }

    /**
     * Set typical-P sampling parameter.
     */
    public function typicalP(float $p): self
    {
        if ($p < 0.0 || $p > 1.0) {
            throw new InvalidArgumentException(
                'Typical-P must be between 0.0 and 1.0.'
            );
        }

        $this->typicalP = $p;
        return $this;
    }

    /**
     * Set prompt variables for prompt templates.
     */
    public function promptVariables(array $variables): self
    {
        $this->promptVariables = $variables;
        return $this;
    }

    /**
     * Create preset parameters for code generation.
     */
    public static function forCodeGeneration(): self
    {
        return (new self())
            ->decodingMethod('greedy')
            ->temperature(Temperature::precise())
            ->maxNewTokens(1000)
            ->stopSequences(['\n\n', '```']);
    }

    /**
     * Create preset parameters for creative writing.
     */
    public static function forCreativeWriting(): self
    {
        return (new self())
            ->decodingMethod('sample')
            ->temperature(Temperature::creative())
            ->topP(0.9)
            ->maxNewTokens(500)
            ->repetitionPenalty(1.1);
    }

    /**
     * Create preset parameters for question answering.
     */
    public static function forQuestionAnswering(): self
    {
        return (new self())
            ->decodingMethod('greedy')
            ->temperature(Temperature::focused())
            ->maxNewTokens(200)
            ->stopSequences(['\n\n']);
    }

    /**
     * Create preset parameters for summarization.
     */
    public static function forSummarization(): self
    {
        return (new self())
            ->decodingMethod('greedy')
            ->temperature(Temperature::focused())
            ->maxNewTokens(300)
            ->repetitionPenalty(1.05);
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        $params = [];

        if ($this->decodingMethod !== null) {
            $params['decoding_method'] = $this->decodingMethod;
        }

        if ($this->maxNewTokens !== null) {
            $params['max_new_tokens'] = $this->maxNewTokens;
        }

        if ($this->minNewTokens !== null) {
            $params['min_new_tokens'] = $this->minNewTokens;
        }

        if ($this->temperature !== null) {
            $params['temperature'] = $this->temperature->toFloat();
        }

        if ($this->topK !== null) {
            $params['top_k'] = $this->topK;
        }

        if ($this->topP !== null) {
            $params['top_p'] = $this->topP;
        }

        if ($this->randomSeed !== null) {
            $params['random_seed'] = $this->randomSeed;
        }

        if ($this->repetitionPenalty !== null) {
            $params['repetition_penalty'] = $this->repetitionPenalty;
        }

        if ($this->stopSequences !== null) {
            $params['stop_sequences'] = $this->stopSequences;
        }

        if ($this->timeLimit !== null) {
            $params['time_limit'] = $this->timeLimit;
        }

        if ($this->typicalP !== null) {
            $params['typical_p'] = $this->typicalP;
        }

        if ($this->promptVariables !== null) {
            $params['prompt_variables'] = $this->promptVariables;
        }

        return $params;
    }

    // Getters
    public function getDecodingMethod(): ?string
    {
        return $this->decodingMethod;
    }

    public function getMaxNewTokens(): ?int
    {
        return $this->maxNewTokens;
    }

    public function getMinNewTokens(): ?int
    {
        return $this->minNewTokens;
    }

    public function getTemperature(): ?Temperature
    {
        return $this->temperature;
    }

    public function getTopK(): ?int
    {
        return $this->topK;
    }

    public function getTopP(): ?float
    {
        return $this->topP;
    }

    public function getRandomSeed(): ?int
    {
        return $this->randomSeed;
    }

    public function getRepetitionPenalty(): ?float
    {
        return $this->repetitionPenalty;
    }

    public function getStopSequences(): ?array
    {
        return $this->stopSequences;
    }

    public function getTimeLimit(): ?int
    {
        return $this->timeLimit;
    }

    public function getTypicalP(): ?float
    {
        return $this->typicalP;
    }

    public function getPromptVariables(): ?array
    {
        return $this->promptVariables;
    }
}