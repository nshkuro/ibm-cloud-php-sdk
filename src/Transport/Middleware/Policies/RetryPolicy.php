<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware\Policies;

use Throwable;

/**
 * Retry policy configuration for RetryMiddleware.
 */
final class RetryPolicy
{
    private int $maxAttempts = 3;
    private float $baseDelay = 1.0;
    private float $maxDelay = 30.0;
    private float $backoffMultiplier = 2.0;
    private bool $jitterEnabled = true;
    private array $retryableExceptions = [];
    private array $retryableStatusCodes = [429, 500, 502, 503, 504];
    private $shouldRetryCallback;

    public function __construct()
    {
        // Default callback that checks status codes and exceptions.
        $this->shouldRetryCallback = function (Throwable $exception, int $attempts): bool {
            return $attempts < $this->maxAttempts;
        };
    }

    /**
     * Create a policy with exponential backoff.
     */
    public static function exponentialBackoff(): self
    {
        return new self();
    }

    /**
     * Create a policy with linear backoff.
     */
    public static function linearBackoff(): self
    {
        $policy = new self();
        $policy->backoffMultiplier = 1.0;
        return $policy;
    }

    /**
     * Create a policy with fixed delay.
     */
    public static function fixedDelay(float $delay = 1.0): self
    {
        $policy = new self();
        $policy->baseDelay = $delay;
        $policy->backoffMultiplier = 1.0;
        $policy->jitterEnabled = false;
        return $policy;
    }

    /**
     * Set maximum number of retry attempts.
     */
    public function maxAttempts(int $attempts): self
    {
        $this->maxAttempts = max(1, $attempts);
        return $this;
    }

    /**
     * Set base delay in seconds.
     */
    public function baseDelay(float $seconds): self
    {
        $this->baseDelay = max(0.001, $seconds);
        return $this;
    }

    /**
     * Set maximum delay in seconds.
     */
    public function maxDelay(float $seconds): self
    {
        $this->maxDelay = max($this->baseDelay, $seconds);
        return $this;
    }

    /**
     * Set backoff multiplier for exponential backoff.
     */
    public function backoffMultiplier(float $multiplier): self
    {
        $this->backoffMultiplier = max(1.0, $multiplier);
        return $this;
    }

    /**
     * Enable or disable jitter.
     */
    public function withJitter(bool $enabled = true): self
    {
        $this->jitterEnabled = $enabled;
        return $this;
    }

    /**
     * Set retryable exception classes.
     */
    public function retryableExceptions(array $exceptions): self
    {
        $this->retryableExceptions = $exceptions;
        return $this;
    }

    /**
     * Set retryable HTTP status codes.
     */
    public function retryableStatusCodes(array $codes): self
    {
        $this->retryableStatusCodes = $codes;
        return $this;
    }

    /**
     * Set custom retry condition callback.
     */
    public function shouldRetry(callable $callback): self
    {
        $this->shouldRetryCallback = $callback;
        return $this;
    }

    /**
     * Calculate delay for the given attempt.
     */
    public function calculateDelay(int $attempt): float
    {
        if ($attempt <= 0) {
            return 0.0;
        }

        $delay = $this->baseDelay * pow($this->backoffMultiplier, $attempt - 1);
        $delay = min($delay, $this->maxDelay);

        if ($this->jitterEnabled) {
            // Add random jitter up to 25% of the delay.
            $jitter = $delay * 0.25 * (mt_rand() / mt_getrandmax());
            $delay += $jitter;
        }

        return $delay;
    }

    /**
     * Check if the exception should trigger a retry.
     */
    public function shouldRetryException(Throwable $exception, int $attempts): bool
    {
        // Check custom callback first.
        if (!($this->shouldRetryCallback)($exception, $attempts)) {
            return false;
        }

        // Check if we've exceeded max attempts.
        if ($attempts >= $this->maxAttempts) {
            return false;
        }

        // Check if exception type is retryable.
        if (!empty($this->retryableExceptions)) {
            foreach ($this->retryableExceptions as $retryableClass) {
                if ($exception instanceof $retryableClass) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Check if the HTTP status code should trigger a retry.
     */
    public function shouldRetryStatusCode(int $statusCode, int $attempts): bool
    {
        if ($attempts >= $this->maxAttempts) {
            return false;
        }

        return in_array($statusCode, $this->retryableStatusCodes, true);
    }

    // Getters.
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getBaseDelay(): float
    {
        return $this->baseDelay;
    }

    public function getMaxDelay(): float
    {
        return $this->maxDelay;
    }

    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    public function isJitterEnabled(): bool
    {
        return $this->jitterEnabled;
    }

    public function getRetryableExceptions(): array
    {
        return $this->retryableExceptions;
    }

    public function getRetryableStatusCodes(): array
    {
        return $this->retryableStatusCodes;
    }
}