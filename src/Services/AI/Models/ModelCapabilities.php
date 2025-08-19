<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models;

use IBMCloud\Services\AI\ValueObjects\ModelId;

final class ModelCapabilities
{
    public function __construct(
        private readonly ModelId $modelId,
        private readonly string $label,
        private readonly string $provider,
        private readonly string $source,
        private readonly ?string $shortDescription = null,
        private readonly array $tasks = [],
        private readonly int $minShotSize = 0,
        private readonly string $inputTier = 'class_1',
        private readonly string $outputTier = 'class_1',
        private readonly ?string $numberParams = null,
        private readonly array $supportedLanguages = [],
        private readonly ?int $maxSequenceLength = null,
        private readonly ?int $contextWindow = null,
        private readonly bool $supportsStreaming = true,
        private readonly bool $supportsEmbeddings = false,
        private readonly array $metadata = []
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            ModelId::from($response['model_id']),
            $response['label'] ?? '',
            $response['provider'] ?? '',
            $response['source'] ?? '',
            $response['short_description'] ?? null,
            $response['tasks'] ?? [],
            $response['min_shot_size'] ?? 0,
            $response['input_tier'] ?? 'class_1',
            $response['output_tier'] ?? 'class_1',
            $response['number_params'] ?? null,
            $response['supported_languages'] ?? [],
            $response['max_sequence_length'] ?? null,
            $response['context_window'] ?? null,
            $response['supports_streaming'] ?? true,
            $response['supports_embeddings'] ?? false,
            $response['metadata'] ?? []
        );
    }

    /**
     * Check if the model supports a specific task.
     */
    public function supportsTask(string $taskId): bool
    {
        foreach ($this->tasks as $task) {
            if (($task['id'] ?? '') === $taskId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get supported task IDs.
     */
    public function getSupportedTaskIds(): array
    {
        return array_map(
            fn(array $task) => $task['id'] ?? '',
            $this->tasks
        );
    }

    /**
     * Get task information by ID.
     */
    public function getTask(string $taskId): ?array
    {
        foreach ($this->tasks as $task) {
            if (($task['id'] ?? '') === $taskId) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Get quality rating for a specific task.
     */
    public function getTaskQuality(string $taskId): ?int
    {
        $task = $this->getTask($taskId);
        return $task['ratings']['quality'] ?? null;
    }

    /**
     * Check if this is a code-specialized model.
     */
    public function isCodeModel(): bool
    {
        return $this->supportsTask('code') || 
               str_contains(strtolower($this->label), 'code') ||
               str_contains(strtolower($this->shortDescription ?? ''), 'code');
    }

    /**
     * Check if this is a chat/instruction model.
     */
    public function isChatModel(): bool
    {
        return $this->supportsTask('conversation') || 
               $this->supportsTask('question_answering') ||
               str_contains(strtolower($this->label), 'instruct') ||
               str_contains(strtolower($this->label), 'chat');
    }

    /**
     * Check if this is a text generation model.
     */
    public function isTextGenerationModel(): bool
    {
        return $this->supportsTask('generation') ||
               $this->supportsTask('text_generation') ||
               !empty($this->tasks); // Most models support some form of text generation
    }

    /**
     * Check if this is a multilingual model.
     */
    public function isMultilingual(): bool
    {
        return count($this->supportedLanguages) > 1;
    }

    /**
     * Check if the model supports a specific language.
     */
    public function supportsLanguage(string $language): bool
    {
        if (empty($this->supportedLanguages)) {
            // If no languages specified, assume it supports common languages
            return in_array(strtolower($language), ['en', 'english'], true);
        }

        return in_array(strtolower($language), 
               array_map('strtolower', $this->supportedLanguages), true);
    }

    /**
     * Get estimated cost tier (higher tier = more expensive).
     */
    public function getCostTier(): int
    {
        // Map tier strings to numeric values for comparison
        $tierMap = [
            'class_1' => 1,
            'class_2' => 2,
            'class_3' => 3,
            'class_4' => 4,
        ];

        $inputCost = $tierMap[$this->inputTier] ?? 1;
        $outputCost = $tierMap[$this->outputTier] ?? 1;

        return max($inputCost, $outputCost);
    }

    /**
     * Get model size category.
     */
    public function getModelSize(): string
    {
        if ($this->numberParams === null) {
            return 'unknown';
        }

        $params = strtolower($this->numberParams);
        
        if (str_contains($params, 't')) {
            return 'trillion'; // T parameters
        }
        
        if (str_contains($params, 'b')) {
            $billion = (float) str_replace('b', '', $params);
            if ($billion >= 100) return 'extra_large';
            if ($billion >= 50) return 'large';
            if ($billion >= 10) return 'medium';
            return 'small';
        }
        
        if (str_contains($params, 'm')) {
            return 'tiny';
        }

        return 'unknown';
    }

    /**
     * Check if model is suitable for production use.
     */
    public function isProductionReady(): bool
    {
        // Check if it's in experimental/preview state
        $experimental = str_contains(strtolower($this->label), 'preview') ||
                       str_contains(strtolower($this->label), 'experimental') ||
                       str_contains(strtolower($this->label), 'alpha') ||
                       str_contains(strtolower($this->label), 'beta');

        return !$experimental;
    }

    /**
     * Get recommended use cases based on capabilities.
     */
    public function getRecommendedUseCases(): array
    {
        $useCases = [];

        if ($this->isCodeModel()) {
            $useCases[] = 'Code generation and completion';
            $useCases[] = 'Code explanation and documentation';
            $useCases[] = 'Bug fixing and optimization';
        }

        if ($this->isChatModel()) {
            $useCases[] = 'Conversational AI and chatbots';
            $useCases[] = 'Question answering systems';
            $useCases[] = 'Customer support automation';
        }

        if ($this->supportsTask('summarization')) {
            $useCases[] = 'Document summarization';
            $useCases[] = 'Content condensation';
        }

        if ($this->supportsTask('classification')) {
            $useCases[] = 'Text classification';
            $useCases[] = 'Sentiment analysis';
            $useCases[] = 'Content categorization';
        }

        if ($this->supportsEmbeddings) {
            $useCases[] = 'Semantic search';
            $useCases[] = 'Document similarity';
            $useCases[] = 'Recommendation systems';
        }

        if (empty($useCases)) {
            $useCases[] = 'General text generation';
        }

        return $useCases;
    }

    /**
     * Get performance characteristics summary.
     */
    public function getPerformanceSummary(): array
    {
        return [
            'model_size' => $this->getModelSize(),
            'cost_tier' => $this->getCostTier(),
            'input_tier' => $this->inputTier,
            'output_tier' => $this->outputTier,
            'context_window' => $this->contextWindow,
            'max_sequence_length' => $this->maxSequenceLength,
            'supports_streaming' => $this->supportsStreaming,
            'supports_embeddings' => $this->supportsEmbeddings,
            'production_ready' => $this->isProductionReady(),
        ];
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function getMinShotSize(): int
    {
        return $this->minShotSize;
    }

    public function getInputTier(): string
    {
        return $this->inputTier;
    }

    public function getOutputTier(): string
    {
        return $this->outputTier;
    }

    public function getNumberParams(): ?string
    {
        return $this->numberParams;
    }

    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    public function getMaxSequenceLength(): ?int
    {
        return $this->maxSequenceLength;
    }

    public function getContextWindow(): ?int
    {
        return $this->contextWindow;
    }

    public function getSupportsStreaming(): bool
    {
        return $this->supportsStreaming;
    }

    public function getSupportsEmbeddings(): bool
    {
        return $this->supportsEmbeddings;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}