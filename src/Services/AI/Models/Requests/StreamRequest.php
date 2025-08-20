<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Requests;

use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;

final class StreamRequest extends CompletionRequest
{
    private bool $includeStopReason = true;
    private bool $includeTokenInfo = false;

    /**
     * Create a new stream request with fluent interface.
     * 
     * @param ModelId $modelId The model to use for generation
     * @param Prompt $prompt The input prompt
     * @param string|null $projectId Optional project ID
     * @return self New StreamRequest instance
     */
    public static function create(ModelId $modelId, Prompt $prompt, ?string $projectId = null): self
    {
        $request = new self($modelId, $prompt, $projectId);
        return $request;
    }

    /**
     * Override withParameters to return StreamRequest.
     * 
     * @param GenerationParameters $parameters Generation parameters to apply
     * @return self New StreamRequest instance with updated parameters
     */
    public function withParameters(GenerationParameters $parameters): self
    {
        $parent = parent::withParameters($parameters);
        return $this->cloneFromParent($parent);
    }

    /**
     * Override parent method to return StreamRequest instance.
     * 
     * @param string $projectId The project ID to set
     * @return self New StreamRequest instance with updated project ID
     */
    public function withProjectId(string $projectId): self
    {
        $parent = parent::withProjectId($projectId);
        return $this->cloneFromParent($parent);
    }

    /**
     * Create a new StreamRequest instance from parent CompletionRequest.
     * 
     * This method eliminates code duplication by centralizing the cloning logic.
     * 
     * @param CompletionRequest $parent Parent request to clone from
     * @return self New StreamRequest with preserved streaming settings
     */
    private function cloneFromParent(CompletionRequest $parent): self
    {
        $new = new self(
            $parent->getModelId(),
            $parent->getPrompt(),
            $parent->getProjectId(),
            $parent->getSpaceId(),
            $parent->getParameters(),
            $parent->getModerations(),
            $parent->getMetadata()
        );
        
        // Preserve streaming-specific settings.
        $new->includeStopReason = $this->includeStopReason;
        $new->includeTokenInfo = $this->includeTokenInfo;
        
        return $new;
    }

    /**
     * Enable/disable stop reason in streaming response.
     */
    public function withStopReason(bool $include = true): self
    {
        $new = clone $this;
        $new->includeStopReason = $include;
        return $new;
    }

    /**
     * Enable/disable token information in streaming response.
     */
    public function withTokenInfo(bool $include = true): self
    {
        $new = clone $this;
        $new->includeTokenInfo = $include;
        return $new;
    }

    /**
     * Convert to array for streaming API request.
     */
    public function toArray(): array
    {
        $request = parent::toArray();

        // Add streaming-specific options
        if ($this->includeStopReason) {
            $request['return_options']['include_stop_reason'] = true;
        }

        if ($this->includeTokenInfo) {
            $request['return_options']['include_generated_tokens'] = true;
            $request['return_options']['include_input_tokens'] = true;
        }

        return $request;
    }

    public function shouldIncludeStopReason(): bool
    {
        return $this->includeStopReason;
    }

    public function shouldIncludeTokenInfo(): bool
    {
        return $this->includeTokenInfo;
    }
}