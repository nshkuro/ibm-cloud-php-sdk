<?php

declare(strict_types=1);

namespace IBMCloud\Contracts\Services;

use IBMCloud\Services\AI\Models\Requests\CompletionRequest;
use IBMCloud\Services\AI\Models\Requests\EmbeddingRequest;
use IBMCloud\Services\AI\Models\Requests\StreamRequest;
use IBMCloud\Services\AI\Models\Results\CompletionResult;
use IBMCloud\Services\AI\Models\Results\EmbeddingResult;
use IBMCloud\Services\AI\Models\Results\StreamResult;
use IBMCloud\Services\AI\Models\ModelCapabilities;

interface AIModelInterface
{
    /**
     * Generate text completion.
     */
    public function complete(CompletionRequest $request): CompletionResult;

    /**
     * Generate text completion with streaming.
     */
    public function stream(StreamRequest $request): StreamResult;

    /**
     * Generate text embeddings.
     */
    public function embed(EmbeddingRequest $request): EmbeddingResult;

    /**
     * Get model capabilities and information.
     */
    public function capabilities(): ModelCapabilities;

    /**
     * List available foundation models.
     */
    public function listModels(): array;

    /**
     * Get information about a specific model.
     */
    public function getModel(string $modelId): array;
}