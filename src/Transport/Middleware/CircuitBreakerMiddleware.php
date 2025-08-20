<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware;

use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Transport\Middleware\Policies\CircuitBreakerPolicy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Circuit breaker middleware that prevents requests to failing services.
 */
final class CircuitBreakerMiddleware implements MiddlewareInterface
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $successCount = 0;
    private float $lastFailureTime = 0;

    public function __construct(
        private readonly CircuitBreakerPolicy $policy,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $this->updateState();

        if ($this->state === self::STATE_OPEN) {
            $this->logger->warning('Circuit breaker is open, rejecting request', [
                'state' => $this->state,
                'failure_count' => $this->failureCount,
                'uri' => (string) $request->getUri(),
            ]);

            throw new RuntimeException(
                'Circuit breaker is open - service appears to be failing'
            );
        }

        try {
            $response = $next($request);
            $this->recordSuccess($response);
            return $response;

        } catch (Throwable $exception) {
            $this->recordFailure($exception, $request);
            throw $exception;
        }
    }

    /**
     * Update circuit breaker state based on time and current state.
     */
    private function updateState(): void
    {
        if ($this->state === self::STATE_OPEN) {
            $timeoutExpired = (microtime(true) - $this->lastFailureTime) >= $this->policy->getTimeout();
            
            if ($timeoutExpired) {
                $this->state = self::STATE_HALF_OPEN;
                $this->successCount = 0;
                
                $this->logger->info('Circuit breaker transitioning to half-open', [
                    'previous_state' => self::STATE_OPEN,
                    'new_state' => self::STATE_HALF_OPEN,
                    'timeout_seconds' => $this->policy->getTimeout(),
                ]);
            }
        }
    }

    /**
     * Record a successful response.
     */
    private function recordSuccess(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        // Check if status code indicates failure.
        if ($this->policy->isFailureStatusCode($statusCode)) {
            $this->recordFailureFromStatusCode($statusCode);
            return;
        }

        // Record success.
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->successCount++;
            
            $this->logger->debug('Circuit breaker recorded success in half-open state', [
                'success_count' => $this->successCount,
                'success_threshold' => $this->policy->getSuccessThreshold(),
                'status_code' => $statusCode,
            ]);

            if ($this->successCount >= $this->policy->getSuccessThreshold()) {
                $this->state = self::STATE_CLOSED;
                $this->failureCount = 0;
                $this->successCount = 0;
                
                $this->logger->info('Circuit breaker closed after successful requests', [
                    'previous_state' => self::STATE_HALF_OPEN,
                    'new_state' => self::STATE_CLOSED,
                    'success_count' => $this->successCount,
                ]);
            }
        } elseif ($this->state === self::STATE_CLOSED && $this->failureCount > 0) {
            // Reset failure count on success in closed state.
            $this->failureCount = 0;
            
            $this->logger->debug('Circuit breaker reset failure count after success', [
                'state' => $this->state,
                'status_code' => $statusCode,
            ]);
        }
    }

    /**
     * Record a failure from an exception.
     */
    private function recordFailure(Throwable $exception, RequestInterface $request): void
    {
        if (!$this->policy->isFailureException($exception)) {
            return; // Not a monitored exception.
        }

        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        $this->logger->warning('Circuit breaker recorded failure', [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'failure_threshold' => $this->policy->getFailureThreshold(),
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'uri' => (string) $request->getUri(),
        ]);

        if ($this->failureCount >= $this->policy->getFailureThreshold()) {
            $this->openCircuit();
        }
    }

    /**
     * Record a failure from HTTP status code.
     */
    private function recordFailureFromStatusCode(int $statusCode): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        $this->logger->warning('Circuit breaker recorded failure from status code', [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'failure_threshold' => $this->policy->getFailureThreshold(),
            'status_code' => $statusCode,
        ]);

        if ($this->failureCount >= $this->policy->getFailureThreshold()) {
            $this->openCircuit();
        }
    }

    /**
     * Open the circuit breaker.
     */
    private function openCircuit(): void
    {
        $previousState = $this->state;
        $this->state = self::STATE_OPEN;
        $this->successCount = 0;

        $this->logger->error('Circuit breaker opened due to failure threshold', [
            'previous_state' => $previousState,
            'new_state' => self::STATE_OPEN,
            'failure_count' => $this->failureCount,
            'failure_threshold' => $this->policy->getFailureThreshold(),
            'timeout_seconds' => $this->policy->getTimeout(),
        ]);
    }

    /**
     * Get current circuit breaker state.
     */
    public function getState(): string
    {
        $this->updateState();
        return $this->state;
    }

    /**
     * Get current failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get current success count (relevant in half-open state).
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Reset circuit breaker to closed state.
     */
    public function reset(): void
    {
        $this->logger->info('Circuit breaker manually reset', [
            'previous_state' => $this->state,
            'previous_failure_count' => $this->failureCount,
        ]);

        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->lastFailureTime = 0;
    }

    /**
     * Get the circuit breaker policy.
     */
    public function getPolicy(): CircuitBreakerPolicy
    {
        return $this->policy;
    }

    /**
     * Create middleware with default policy.
     */
    public static function create(
        int $failureThreshold = 5,
        float $timeout = 60.0,
        ?LoggerInterface $logger = null
    ): self {
        $policy = CircuitBreakerPolicy::create()
            ->failureThreshold($failureThreshold)
            ->timeout($timeout);

        return new self($policy, $logger ?? new NullLogger());
    }
}