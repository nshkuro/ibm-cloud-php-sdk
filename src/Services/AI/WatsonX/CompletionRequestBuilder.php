<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\WatsonX;

use IBMCloud\Services\AI\Models\Requests\CompletionRequest;
use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;
use IBMCloud\Services\AI\ValueObjects\Temperature;
use InvalidArgumentException;

final class CompletionRequestBuilder
{
    private ?ModelId $modelId = null;
    private ?Prompt $prompt = null;
    private ?string $projectId = null;
    private ?string $spaceId = null;
    private ?GenerationParameters $parameters = null;
    private array $moderations = [];
    private array $metadata = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the model to use.
     */
    public function model(ModelId $modelId): self
    {
        $this->modelId = $modelId;
        return $this;
    }

    /**
     * Set the model using string ID.
     */
    public function modelId(string $modelId): self
    {
        return $this->model(ModelId::from($modelId));
    }

    /**
     * Use IBM Granite 3B Code model.
     */
    public function granite3BCode(): self
    {
        return $this->model(ModelId::granite3BCode());
    }

    /**
     * Use IBM Granite 8B Code model.
     */
    public function granite8BCode(): self
    {
        return $this->model(ModelId::granite8BCode());
    }

    /**
     * Use IBM Granite 20B Code model.
     */
    public function granite20BCode(): self
    {
        return $this->model(ModelId::granite20BCode());
    }

    /**
     * Use IBM Granite 34B Code model.
     */
    public function granite34BCode(): self
    {
        return $this->model(ModelId::granite34BCode());
    }

    /**
     * Use Llama 3 8B model.
     */
    public function llama3_8B(): self
    {
        return $this->model(ModelId::llama3_8B());
    }

    /**
     * Use Llama 3 70B model.
     */
    public function llama3_70B(): self
    {
        return $this->model(ModelId::llama3_70B());
    }

    /**
     * Use Llama 3.3 70B model.
     */
    public function llama3_3_70B(): self
    {
        return $this->model(ModelId::llama3_3_70B());
    }

    /**
     * Use Mistral 7B model.
     */
    public function mistral7B(): self
    {
        return $this->model(ModelId::mistral7B());
    }

    /**
     * Use Mixtral 8x7B model.
     */
    public function mixtral8x7B(): self
    {
        return $this->model(ModelId::mixtral8x7B());
    }

    /**
     * Set the prompt.
     */
    public function prompt(Prompt $prompt): self
    {
        $this->prompt = $prompt;
        return $this;
    }

    /**
     * Set prompt from string.
     */
    public function promptText(string $text): self
    {
        return $this->prompt(Prompt::from($text));
    }

    /**
     * Set system message prompt.
     */
    public function systemMessage(string $content): self
    {
        return $this->prompt(Prompt::systemMessage($content));
    }

    /**
     * Set user message prompt.
     */
    public function userMessage(string $content): self
    {
        return $this->prompt(Prompt::userMessage($content));
    }

    /**
     * Set chat-formatted prompt.
     */
    public function chat(array $messages): self
    {
        return $this->prompt(Prompt::chat($messages));
    }

    /**
     * Set context and question prompt.
     */
    public function withContext(string $context, string $question): self
    {
        return $this->prompt(Prompt::withContext($context, $question));
    }

    /**
     * Set few-shot prompt with examples.
     */
    public function fewShot(string $task, array $examples, string $input): self
    {
        return $this->prompt(Prompt::fewShot($task, $examples, $input));
    }

    /**
     * Set project ID.
     */
    public function projectId(string $projectId): self
    {
        $this->projectId = $projectId;
        return $this;
    }

    /**
     * Set space ID.
     */
    public function spaceId(string $spaceId): self
    {
        $this->spaceId = $spaceId;
        return $this;
    }

    /**
     * Set generation parameters.
     */
    public function parameters(GenerationParameters $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set temperature.
     */
    public function temperature(Temperature $temperature): self
    {
        $this->ensureParameters();
        $this->parameters->temperature($temperature);
        return $this;
    }

    /**
     * Set temperature from float value.
     */
    public function temperatureValue(float $temperature): self
    {
        return $this->temperature(Temperature::from($temperature));
    }

    /**
     * Use precise temperature (0.0).
     */
    public function precise(): self
    {
        return $this->temperature(Temperature::precise());
    }

    /**
     * Use focused temperature (0.3).
     */
    public function focused(): self
    {
        return $this->temperature(Temperature::focused());
    }

    /**
     * Use balanced temperature (0.7).
     */
    public function balanced(): self
    {
        return $this->temperature(Temperature::balanced());
    }

    /**
     * Use creative temperature (1.0).
     */
    public function creative(): self
    {
        return $this->temperature(Temperature::creative());
    }

    /**
     * Set maximum new tokens.
     */
    public function maxTokens(int $tokens): self
    {
        $this->ensureParameters();
        $this->parameters->maxNewTokens($tokens);
        return $this;
    }

    /**
     * Set minimum new tokens.
     */
    public function minTokens(int $tokens): self
    {
        $this->ensureParameters();
        $this->parameters->minNewTokens($tokens);
        return $this;
    }

    /**
     * Set top-K sampling.
     */
    public function topK(int $k): self
    {
        $this->ensureParameters();
        $this->parameters->topK($k);
        return $this;
    }

    /**
     * Set top-P (nucleus) sampling.
     */
    public function topP(float $p): self
    {
        $this->ensureParameters();
        $this->parameters->topP($p);
        return $this;
    }

    /**
     * Set random seed for reproducibility.
     */
    public function seed(int $seed): self
    {
        $this->ensureParameters();
        $this->parameters->randomSeed($seed);
        return $this;
    }

    /**
     * Set repetition penalty.
     */
    public function repetitionPenalty(float $penalty): self
    {
        $this->ensureParameters();
        $this->parameters->repetitionPenalty($penalty);
        return $this;
    }

    /**
     * Set stop sequences.
     */
    public function stopSequences(array $sequences): self
    {
        $this->ensureParameters();
        $this->parameters->stopSequences($sequences);
        return $this;
    }

    /**
     * Set time limit in milliseconds.
     */
    public function timeLimit(int $milliseconds): self
    {
        $this->ensureParameters();
        $this->parameters->timeLimit($milliseconds);
        return $this;
    }

    /**
     * Enable HAP (Hate, Abuse, Profanity) detection.
     */
    public function withHAPDetection(bool $enabled = true, float $threshold = 0.5): self
    {
        $this->moderations['hap'] = [
            'output' => [
                'enabled' => $enabled,
                'threshold' => $threshold,
            ],
        ];
        
        return $this;
    }

    /**
     * Enable PII (Personally Identifiable Information) detection.
     */
    public function withPIIDetection(bool $enabled = true, bool $maskEntities = true): self
    {
        $this->moderations['pii'] = [
            'output' => [
                'enabled' => $enabled,
            ],
            'mask' => [
                'remove_entity_value' => $maskEntities,
            ],
        ];
        
        return $this;
    }

    /**
     * Add metadata.
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Use preset parameters for code generation.
     */
    public function forCodeGeneration(): self
    {
        return $this->parameters(GenerationParameters::forCodeGeneration());
    }

    /**
     * Use preset parameters for creative writing.
     */
    public function forCreativeWriting(): self
    {
        return $this->parameters(GenerationParameters::forCreativeWriting());
    }

    /**
     * Use preset parameters for question answering.
     */
    public function forQuestionAnswering(): self
    {
        return $this->parameters(GenerationParameters::forQuestionAnswering());
    }

    /**
     * Use preset parameters for summarization.
     */
    public function forSummarization(): self
    {
        return $this->parameters(GenerationParameters::forSummarization());
    }

    /**
     * Build the completion request.
     */
    public function build(): CompletionRequest
    {
        $this->validate();

        $request = new CompletionRequest(
            $this->modelId,
            $this->prompt,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            !empty($this->moderations) ? $this->moderations : null,
            !empty($this->metadata) ? $this->metadata : null
        );

        return $request;
    }

    /**
     * Ensure parameters object exists.
     */
    private function ensureParameters(): void
    {
        if ($this->parameters === null) {
            $this->parameters = new GenerationParameters();
        }
    }

    /**
     * Validate the builder state.
     */
    private function validate(): void
    {
        if ($this->modelId === null) {
            throw new InvalidArgumentException('Model ID must be specified.');
        }

        if ($this->prompt === null) {
            throw new InvalidArgumentException('Prompt must be specified.');
        }

        if ($this->projectId === null && $this->spaceId === null) {
            throw new InvalidArgumentException('Either project ID or space ID must be specified.');
        }

        if ($this->projectId !== null && $this->spaceId !== null) {
            throw new InvalidArgumentException('Cannot specify both project ID and space ID.');
        }
    }
}