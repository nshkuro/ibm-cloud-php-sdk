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