<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware;

use IBMCloud\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Rate limiting middleware using token bucket algorithm.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private float $lastRefillTime;
    private float $tokens;

    public function __construct(
        private readonly int $capacity,
        private readonly float $refillRate,
        private readonly float $maxWaitTime = 10.0,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        if ($capacity <= 0) {
            throw new \InvalidArgumentException('Capacity must be greater than 0');
        }
        
        if ($refillRate <= 0) {
            throw new \InvalidArgumentException('Refill rate must be greater than 0');
        }

        $this->tokens = $capacity;
        $this->lastRefillTime = microtime(true);
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $this->refillTokens();

        if ($this->tokens >= 1.0) {
            // We have tokens available.
            $this->tokens -= 1.0;
            
            $this->logger->debug('Rate limit: token consumed', [
                'remaining_tokens' => $this->tokens,
                'capacity' => $this->capacity,
                'uri' => (string) $request->getUri(),
            ]);

            return $next($request);
        }

        // No tokens available - try to wait for refill.
        $waitTime = $this->calculateWaitTime();
        
        if ($waitTime > $this->maxWaitTime) {
            $this->logger->warning('Rate limit exceeded, request rejected', [
                'required_wait_time' => $waitTime,
                'max_wait_time' => $this->maxWaitTime,
                'tokens' => $this->tokens,
                'uri' => (string) $request->getUri(),
            ]);

            throw new RuntimeException(
                sprintf(
                    'Rate limit exceeded. Required wait time (%.2fs) exceeds maximum (%.2fs)',
                    $waitTime,
                    $this->maxWaitTime
                )
            );
        }

        // Wait for token refill.
        $this->logger->info('Rate limit: waiting for token refill', [
            'wait_time' => $waitTime,
            'tokens' => $this->tokens,
            'uri' => (string) $request->getUri(),
        ]);

        usleep((int) ($waitTime * 1_000_000));
        
        // Refill and try again.
        $this->refillTokens();
        
        if ($this->tokens >= 1.0) {
            $this->tokens -= 1.0;
            
            $this->logger->debug('Rate limit: token consumed after wait', [
                'remaining_tokens' => $this->tokens,
                'waited_seconds' => $waitTime,
                'uri' => (string) $request->getUri(),
            ]);

            return $next($request);
        }

        // Still no tokens after waiting.
        $this->logger->error('Rate limit: no tokens available after waiting', [
            'waited_seconds' => $waitTime,
            'tokens' => $this->tokens,
            'uri' => (string) $request->getUri(),
        ]);

        throw new RuntimeException('Rate limit exceeded - no tokens available after waiting');
    }

    /**
     * Refill tokens based on elapsed time.
     */
    private function refillTokens(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefillTime;
        
        if ($elapsed > 0) {
            $tokensToAdd = $elapsed * $this->refillRate;
            $this->tokens = min($this->capacity, $this->tokens + $tokensToAdd);
            $this->lastRefillTime = $now;
            
            if ($tokensToAdd > 0.001) { // Only log significant refills.
                $this->logger->debug('Rate limit: tokens refilled', [
                    'tokens_added' => $tokensToAdd,
                    'current_tokens' => $this->tokens,
                    'capacity' => $this->capacity,
                    'elapsed_seconds' => $elapsed,
                ]);
            }
        }
    }

    /**
     * Calculate how long to wait for at least one token.
     */
    private function calculateWaitTime(): float
    {
        $tokensNeeded = 1.0 - $this->tokens;
        return $tokensNeeded / $this->refillRate;
    }

    /**
     * Get current number of available tokens.
     */
    public function getAvailableTokens(): float
    {
        $this->refillTokens();
        return $this->tokens;
    }

    /**
     * Get bucket capacity.
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * Get refill rate (tokens per second).
     */
    public function getRefillRate(): float
    {
        return $this->refillRate;
    }

    /**
     * Get maximum wait time.
     */
    public function getMaxWaitTime(): float
    {
        return $this->maxWaitTime;
    }

    /**
     * Create rate limiter with requests per second.
     */
    public static function perSecond(
        int $requestsPerSecond,
        ?int $burstCapacity = null,
        float $maxWaitTime = 10.0,
        ?LoggerInterface $logger = null
    ): self {
        $capacity = $burstCapacity ?? $requestsPerSecond;
        
        return new self(
            $capacity,
            $requestsPerSecond,
            $maxWaitTime,
            $logger ?? new NullLogger()
        );
    }

    /**
     * Create rate limiter with requests per minute.
     */
    public static function perMinute(
        int $requestsPerMinute,
        ?int $burstCapacity = null,
        float $maxWaitTime = 10.0,
        ?LoggerInterface $logger = null
    ): self {
        $refillRate = $requestsPerMinute / 60.0;
        $capacity = $burstCapacity ?? min($requestsPerMinute, 60);
        
        return new self(
            $capacity,
            $refillRate,
            $maxWaitTime,
            $logger ?? new NullLogger()
        );
    }

    /**
     * Create rate limiter with requests per hour.
     */
    public static function perHour(
        int $requestsPerHour,
        ?int $burstCapacity = null,
        float $maxWaitTime = 30.0,
        ?LoggerInterface $logger = null
    ): self {
        $refillRate = $requestsPerHour / 3600.0;
        $capacity = $burstCapacity ?? min($requestsPerHour, 100);
        
        return new self(
            $capacity,
            $refillRate,
            $maxWaitTime,
            $logger ?? new NullLogger()
        );
    }
}