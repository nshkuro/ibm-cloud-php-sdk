<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\ValueObjects;

use InvalidArgumentException;

final class ModelId
{
    public const GRANITE_3B_CODE = 'ibm/granite-3b-code-instruct';
    public const GRANITE_8B_CODE = 'ibm/granite-8b-code-instruct'; 
    public const GRANITE_20B_CODE = 'ibm/granite-20b-code-instruct';
    public const GRANITE_34B_CODE = 'ibm/granite-34b-code-instruct';
    
    public const LLAMA_3_8B = 'meta-llama/llama-3-8b-instruct';
    public const LLAMA_3_70B = 'meta-llama/llama-3-70b-instruct';
    public const LLAMA_3_3_70B = 'meta-llama/llama-3-3-70b-instruct';
    
    public const MISTRAL_7B = 'mistralai/mistral-7b-instruct-v0-3';
    public const MIXTRAL_8X7B = 'mistralai/mixtral-8x7b-instruct-v01';
    
    public const FLAN_T5_XXL = 'google/flan-t5-xxl';
    public const FLAN_UL2 = 'google/flan-ul2';

    private function __construct(
        public string $value
    ) {
        if (empty($this->value)) {
            throw new InvalidArgumentException('Model ID cannot be empty.');
        }

        if (!$this->isValidFormat($this->value)) {
            throw new InvalidArgumentException(
                'Model ID must be in format "provider/model-name" (e.g., "ibm/granite-3b-code-instruct").'
            );
        }
    }

    public static function from(string $modelId): self
    {
        return new self($modelId);
    }

    public static function granite3BCode(): self
    {
        return new self(self::GRANITE_3B_CODE);
    }

    public static function granite8BCode(): self
    {
        return new self(self::GRANITE_8B_CODE);
    }

    public static function granite20BCode(): self
    {
        return new self(self::GRANITE_20B_CODE);
    }

    public static function granite34BCode(): self
    {
        return new self(self::GRANITE_34B_CODE);
    }

    public static function llama3_8B(): self
    {
        return new self(self::LLAMA_3_8B);
    }

    public static function llama3_70B(): self
    {
        return new self(self::LLAMA_3_70B);
    }

    public static function llama3_3_70B(): self
    {
        return new self(self::LLAMA_3_3_70B);
    }

    public static function mistral7B(): self
    {
        return new self(self::MISTRAL_7B);
    }

    public static function mixtral8x7B(): self
    {
        return new self(self::MIXTRAL_8X7B);
    }

    public static function flanT5XXL(): self
    {
        return new self(self::FLAN_T5_XXL);
    }

    public static function flanUL2(): self
    {
        return new self(self::FLAN_UL2);
    }

    /**
     * Get the provider part of the model ID.
     */
    public function getProvider(): string
    {
        return explode('/', $this->value)[0];
    }

    /**
     * Get the model name part of the model ID.
     */
    public function getModelName(): string
    {
        return explode('/', $this->value, 2)[1] ?? '';
    }

    /**
     * Check if this is an IBM Granite model.
     */
    public function isGranite(): bool
    {
        return str_starts_with($this->value, 'ibm/granite');
    }

    /**
     * Check if this is a code-focused model.
     */
    public function isCodeModel(): bool
    {
        return str_contains($this->value, 'code');
    }

    /**
     * Check if this is an instruction-tuned model.
     */
    public function isInstructModel(): bool
    {
        return str_contains($this->value, 'instruct');
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private function isValidFormat(string $modelId): bool
    {
        // Basic format validation: provider/model-name
        return preg_match('/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/', $modelId) === 1;
    }
}