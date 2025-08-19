<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Transport\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IBMCloud\Transport\Middleware\LoggingMiddleware;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class LoggingMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLogsRequestAndResponse(): void
    {
        $request = new Request('GET', 'https://api.example.com/v1/test', [
            'Authorization' => 'Bearer secret-token',
            'Content-Type' => 'application/json',
        ]);
        
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Set-Cookie' => 'session=secret-cookie'
        ], '{"success": true}');
        
        $logger = Mockery::mock(LoggerInterface::class);
        
        // Expect request log.
        $logger->shouldReceive('info')
            ->once()
            ->with('HTTP Request', [
                'method' => 'GET',
                'uri' => 'https://api.example.com/v1/test',
                'headers' => [
                    'Authorization' => ['***REDACTED***'],
                    'Content-Type' => ['application/json'],
                    'Host' => ['api.example.com'],
                ],
            ]);
        
        // Expect response log.
        $logger->shouldReceive('info')
            ->once()
            ->with('HTTP Response', Mockery::on(function ($data) {
                return $data['status'] === 200
                    && isset($data['duration'])
                    && str_ends_with($data['duration'], 'ms')
                    && $data['headers']['Content-Type'] === ['application/json']
                    && $data['headers']['Set-Cookie'] === ['***REDACTED***'];
            }));
        
        $middleware = new LoggingMiddleware($logger);
        
        $next = function () use ($response) {
            return $response;
        };
        
        $actualResponse = $middleware->process($request, $next);
        
        $this->assertSame($response, $actualResponse);
    }

    public function testWorksWithoutLogger(): void
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200);
        
        $middleware = new LoggingMiddleware();
        
        $next = function () use ($response) {
            return $response;
        };
        
        $actualResponse = $middleware->process($request, $next);
        
        $this->assertSame($response, $actualResponse);
    }

    public function testSanitizesSensitiveHeaders(): void
    {
        $request = new Request('POST', 'https://example.com', [
            'Authorization' => 'Bearer token123',
            'X-API-Key' => 'key123',
            'Cookie' => 'session=value',
            'Content-Type' => 'application/json',
        ]);
        
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('HTTP Request', Mockery::on(function ($data) {
                $headers = $data['headers'];
                return $headers['Authorization'] === ['***REDACTED***']
                    && $headers['X-API-Key'] === ['***REDACTED***']
                    && $headers['Cookie'] === ['***REDACTED***']
                    && $headers['Content-Type'] === ['application/json'];
            }));
        
        $logger->shouldReceive('info')->once(); // Response log
        
        $middleware = new LoggingMiddleware($logger);
        
        $response = $middleware->process($request, fn($req) => new Response(200));
        
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}