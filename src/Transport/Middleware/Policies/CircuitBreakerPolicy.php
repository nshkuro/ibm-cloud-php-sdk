<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware\Policies;

/**
 * Circuit breaker policy configuration.
 */
final class CircuitBreakerPolicy
{
    private int $failureThreshold = 5;
    private int $successThreshold = 3;
    private float $timeout = 60.0;
    private array $monitoredStatusCodes = [500, 502, 503, 504];
    private array $monitoredExceptions = [];

    /**
     * Create a default circuit breaker policy.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the number of failures required to open the circuit.
     */
    public function failureThreshold(int $threshold): self
    {
        $this->failureThreshold = max(1, $threshold);
        return $this;
    }

    /**
     * Set the number of successes required to close the circuit from half-open state.
     */
    public function successThreshold(int $threshold): self
    {
        $this->successThreshold = max(1, $threshold);
        return $this;
    }

    /**
     * Set the timeout in seconds before transitioning from open to half-open.
     */
    public function timeout(float $seconds): self
    {
        $this->timeout = max(0.1, $seconds);
        return $this;
    }

    /**
     * Set HTTP status codes that count as failures.
     */
    public function monitoredStatusCodes(array $codes): self
    {
        $this->monitoredStatusCodes = $codes;
        return $this;
    }

    /**
     * Set exception classes that count as failures.
     */
    public function monitoredExceptions(array $exceptions): self
    {
        $this->monitoredExceptions = $exceptions;
        return $this;
    }

    /**
     * Check if status code should be counted as failure.
     */
    public function isFailureStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, $this->monitoredStatusCodes, true);
    }

    /**
     * Check if exception should be counted as failure.
     */
    public function isFailureException(\Throwable $exception): bool
    {
        if (empty($this->monitoredExceptions)) {
            return true; // Count all exceptions as failures by default.
        }

        foreach ($this->monitoredExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    // Getters.
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getMonitoredStatusCodes(): array
    {
        return $this->monitoredStatusCodes;
    }

    public function getMonitoredExceptions(): array
    {
        return $this->monitoredExceptions;
    }
}