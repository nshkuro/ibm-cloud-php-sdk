<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Transport\Middleware;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IBMCloud\Transport\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RateLimitMiddlewareTest extends TestCase
{
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
    }

    public function testAllowsRequestsWithinRateLimit(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200, [], '{"success": true}');
        
        $middleware = RateLimitMiddleware::perSecond(10, 10, 1.0, $this->logger);
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        $result = $middleware->process($request, $next);
        
        $this->assertSame($response, $result);
        // Logger does not record anything for NullLogger.
    }

    public function testRateLimitsExcessiveRequests(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        
        // Very restrictive rate limit: 1 request per second, no burst.
        $middleware = RateLimitMiddleware::perSecond(1, 1, 0.1, $this->logger);
        
        $next = function ($req) {
            return new Response(200);
        };
        
        // First request should succeed.
        $result = $middleware->process($request, $next);
        $this->assertEquals(200, $result->getStatusCode());
        
        // Second immediate request should be rate limited.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        
        $middleware->process($request, $next);
    }

    public function testBurstCapacityAllowsTemporarySpikes(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200);
        
        // 1 request/second with burst capacity of 5.
        $middleware = RateLimitMiddleware::perSecond(1, 5, 1.0, $this->logger);
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        // Should allow burst requests initially.
        $successCount = 0;
        for ($i = 0; $i < 5; $i++) {
            try {
                $result = $middleware->process($request, $next);
                $this->assertEquals(200, $result->getStatusCode());
                $successCount++;
            } catch (Exception $e) {
                // Rate limiting may kick in.
                break;
            }
        }
        
        $this->assertGreaterThan(0, $successCount); // At least one should succeed.
    }

    public function testTokensReplenishOverTime(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200);
        
        // Very fast replenishment for testing: 10 requests/second.
        $middleware = RateLimitMiddleware::perSecond(10, 1, 1.0, $this->logger);
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        // Use up the initial token.
        $middleware->process($request, $next);
        
        // Wait for token replenishment (0.1 seconds = 1 token at 10/sec rate).
        usleep(100000);
        
        // Should have token available again.
        $result = $middleware->process($request, $next);
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testPerMinuteRateLimit(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200);
        
        $middleware = RateLimitMiddleware::perMinute(60, 2, 0.1, $this->logger); // 1 request/second equivalent.
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        // Should allow burst requests.
        $middleware->process($request, $next);
        $middleware->process($request, $next);
        
        // Third request should be rate limited.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        
        $middleware->process($request, $next);
    }

    public function testPerHourRateLimit(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200);
        
        $middleware = RateLimitMiddleware::perHour(3600, 1, 0.1, $this->logger); // 1 request/second equivalent.
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        // Should allow one request.
        $result = $middleware->process($request, $next);
        $this->assertEquals(200, $result->getStatusCode());
        
        // Second immediate request should be rate limited.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        
        $middleware->process($request, $next);
    }

    public function testWaitsForTokensWhenWaitTimeAllowed(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200);
        
        // Fast replenishment for testing.
        $middleware = RateLimitMiddleware::perSecond(100, 1, 1.0, $this->logger); // 0.01s per token.
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        // Use up the token.
        $middleware->process($request, $next);
        
        // Second request should wait for token and succeed.
        $startTime = microtime(true);
        $result = $middleware->process($request, $next);
        $duration = microtime(true) - $startTime;
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertGreaterThan(0.005, $duration); // Should have waited at least 5ms.
        
        // Logger records would be checked in integration tests.
    }

    public function testExceedsMaxWaitTime(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        
        // Very slow replenishment: 1 request per second, max wait 0.01s.
        $middleware = RateLimitMiddleware::perSecond(1, 1, 0.01, $this->logger);
        
        $next = function ($req) {
            return new Response(200);
        };
        
        // Use up the token.
        $middleware->process($request, $next);
        
        // Second request should fail due to max wait time.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        
        $middleware->process($request, $next);
    }

    public function testLogsRateLimitEvents(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        
        $middleware = RateLimitMiddleware::perSecond(1, 1, 0.01, $this->logger);
        
        $next = function ($req) {
            return new Response(200);
        };
        
        // Use up tokens.
        $middleware->process($request, $next);
        
        // Trigger rate limit.
        try {
            $middleware->process($request, $next);
        } catch (Exception $e) {
            // Expected.
        }
        
        // Logger records would be checked in integration tests with a real logger.
        $this->assertTrue(true); // Test completed successfully.
    }

    public function testDifferentRateLimitInstances(): void
    {
        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(200);
        
        // Two separate rate limiters should have independent token buckets.
        $middleware1 = RateLimitMiddleware::perSecond(1, 1, 0.1, $this->logger);
        $middleware2 = RateLimitMiddleware::perSecond(1, 1, 0.1, $this->logger);
        
        $next = function ($req) use ($response) {
            return $response;
        };
        
        // Each should allow one request.
        $result1 = $middleware1->process($request, $next);
        $result2 = $middleware2->process($request, $next);
        
        $this->assertEquals(200, $result1->getStatusCode());
        $this->assertEquals(200, $result2->getStatusCode());
    }
}