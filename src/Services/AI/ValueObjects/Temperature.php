<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\ValueObjects;

use InvalidArgumentException;

final class Temperature
{
    public const MIN_VALUE = 0.0;
    public const MAX_VALUE = 2.0;
    
    public const PRECISE = 0.0;      // Deterministic, same output
    public const FOCUSED = 0.3;      // Low randomness, focused responses
    public const BALANCED = 0.7;     // Balanced creativity and focus
    public const CREATIVE = 1.0;     // Higher creativity
    public const VERY_CREATIVE = 1.5; // Very creative responses
    public const MAXIMUM = 2.0;      // Maximum randomness

    private function __construct(
        public float $value
    ) {
        if ($this->value < self::MIN_VALUE || $this->value > self::MAX_VALUE) {
            throw new InvalidArgumentException(
                sprintf(
                    'Temperature must be between %.1f and %.1f, got %.2f.',
                    self::MIN_VALUE,
                    self::MAX_VALUE,
                    $this->value
                )
            );
        }
    }

    public static function from(float $value): self
    {
        return new self($value);
    }

    public static function precise(): self
    {
        return new self(self::PRECISE);
    }

    public static function focused(): self
    {
        return new self(self::FOCUSED);
    }

    public static function balanced(): self
    {
        return new self(self::BALANCED);
    }

    public static function creative(): self
    {
        return new self(self::CREATIVE);
    }

    public static function veryCreative(): self
    {
        return new self(self::VERY_CREATIVE);
    }

    public static function maximum(): self
    {
        return new self(self::MAXIMUM);
    }

    /**
     * Check if temperature is deterministic (zero).
     */
    public function isDeterministic(): bool
    {
        return $this->value === 0.0;
    }

    /**
     * Check if temperature is low (focused).
     */
    public function isLow(): bool
    {
        return $this->value <= 0.4;
    }

    /**
     * Check if temperature is moderate.
     */
    public function isModerate(): bool
    {
        return $this->value > 0.4 && $this->value <= 1.0;
    }

    /**
     * Check if temperature is high (creative).
     */
    public function isHigh(): bool
    {
        return $this->value > 1.0;
    }

    /**
     * Get description of what this temperature setting means.
     */
    public function getDescription(): string
    {
        return match (true) {
            $this->value === 0.0 => 'Deterministic - same output every time',
            $this->value <= 0.4 => 'Low creativity - focused and consistent responses',
            $this->value <= 1.0 => 'Moderate creativity - balanced responses',
            $this->value <= 1.5 => 'High creativity - more diverse and creative responses',
            default => 'Maximum creativity - highly unpredictable responses',
        };
    }

    /**
     * Get recommended use case for this temperature.
     */
    public function getRecommendedUseCase(): string
    {
        return match (true) {
            $this->value === 0.0 => 'Code generation, factual Q&A, classification',
            $this->value <= 0.4 => 'Technical writing, analysis, structured tasks',
            $this->value <= 1.0 => 'General conversation, creative writing assistance',
            $this->value <= 1.5 => 'Creative writing, brainstorming, storytelling',
            default => 'Experimental creative tasks, idea generation',
        };
    }

    public function toFloat(): float
    {
        return $this->value;
    }
}