<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware;

use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Transport\Middleware\Policies\RetryPolicy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Middleware that implements retry logic with configurable policies.
 */
final class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RetryPolicy $policy,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->policy->getMaxAttempts()) {
            $attempt++;

            try {
                $response = $next($request);

                // Check if response status code indicates we should retry.
                $statusCode = $response->getStatusCode();
                if ($this->policy->shouldRetryStatusCode($statusCode, $attempt)) {
                    $this->logger->warning('Retrying request due to status code', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'max_attempts' => $this->policy->getMaxAttempts(),
                        'uri' => (string) $request->getUri(),
                    ]);

                    if ($attempt < $this->policy->getMaxAttempts()) {
                        $this->sleep($attempt);
                        continue;
                    }
                }

                // Success - return the response.
                if ($attempt > 1) {
                    $this->logger->info('Request succeeded after retry', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'uri' => (string) $request->getUri(),
                    ]);
                }

                return $response;

            } catch (Throwable $exception) {
                $lastException = $exception;

                if (!$this->policy->shouldRetryException($exception, $attempt)) {
                    $this->logger->error('Request failed, not retrying', [
                        'attempt' => $attempt,
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'uri' => (string) $request->getUri(),
                    ]);
                    throw $exception;
                }

                $this->logger->warning('Request failed, retrying', [
                    'attempt' => $attempt,
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'max_attempts' => $this->policy->getMaxAttempts(),
                    'uri' => (string) $request->getUri(),
                ]);

                if ($attempt < $this->policy->getMaxAttempts()) {
                    $this->sleep($attempt);
                }
            }
        }

        // All attempts exhausted.
        $this->logger->error('All retry attempts exhausted', [
            'attempts' => $attempt,
            'max_attempts' => $this->policy->getMaxAttempts(),
            'uri' => (string) $request->getUri(),
            'last_exception' => $lastException ? get_class($lastException) : 'none',
        ]);

        if ($lastException) {
            throw new RuntimeException(
                sprintf(
                    'Request failed after %d attempts. Last error: %s',
                    $this->policy->getMaxAttempts(),
                    $lastException->getMessage()
                ),
                0,
                $lastException
            );
        }

        throw new RuntimeException(
            sprintf('Request failed after %d attempts with no response', $this->policy->getMaxAttempts())
        );
    }

    /**
     * Sleep for the calculated delay.
     */
    private function sleep(int $attempt): void
    {
        $delay = $this->policy->calculateDelay($attempt);
        
        if ($delay > 0) {
            $this->logger->debug('Sleeping before retry', [
                'attempt' => $attempt,
                'delay_seconds' => $delay,
            ]);
            
            // Convert to microseconds for usleep.
            usleep((int) ($delay * 1_000_000));
        }
    }

    /**
     * Create middleware with exponential backoff policy.
     */
    public static function exponentialBackoff(
        int $maxAttempts = 3,
        float $baseDelay = 1.0,
        ?LoggerInterface $logger = null
    ): self {
        $policy = RetryPolicy::exponentialBackoff()
            ->maxAttempts($maxAttempts)
            ->baseDelay($baseDelay);

        return new self($policy, $logger ?? new NullLogger());
    }

    /**
     * Create middleware with fixed delay policy.
     */
    public static function fixedDelay(
        int $maxAttempts = 3,
        float $delay = 1.0,
        ?LoggerInterface $logger = null
    ): self {
        $policy = RetryPolicy::fixedDelay($delay)
            ->maxAttempts($maxAttempts);

        return new self($policy, $logger ?? new NullLogger());
    }

    /**
     * Create middleware with linear backoff policy.
     */
    public static function linearBackoff(
        int $maxAttempts = 3,
        float $baseDelay = 1.0,
        ?LoggerInterface $logger = null
    ): self {
        $policy = RetryPolicy::linearBackoff()
            ->maxAttempts($maxAttempts)
            ->baseDelay($baseDelay);

        return new self($policy, $logger ?? new NullLogger());
    }

    /**
     * Get the retry policy.
     */
    public function getPolicy(): RetryPolicy
    {
        return $this->policy;
    }
}