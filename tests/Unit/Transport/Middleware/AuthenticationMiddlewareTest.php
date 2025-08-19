<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Transport\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IBMCloud\Contracts\AuthenticatorInterface;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthenticationMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testProcessAuthentica​tesRequest(): void
    {
        $originalRequest = new Request('GET', 'https://api.example.com');
        $authenticatedRequest = new Request('GET', 'https://api.example.com', [
            'Authorization' => 'Bearer test-token'
        ]);
        $response = new Response(200, [], '{"success": true}');
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('authenticate')
            ->once()
            ->with($originalRequest)
            ->andReturn($authenticatedRequest);
        
        $middleware = new AuthenticationMiddleware($authenticator);
        
        $next = function ($request) use ($authenticatedRequest, $response) {
            // Verify that the authenticated request is passed to next handler.
            $this->assertSame($authenticatedRequest, $request);
            return $response;
        };
        
        $result = $middleware->process($originalRequest, $next);
        
        $this->assertSame($response, $result);
    }

    public function testProcessPassesAuthenticatedRequestToNext(): void
    {
        $request = new Request('POST', 'https://api.example.com/data');
        $modifiedRequest = $request->withHeader('X-API-Key', 'secret-key');
        $expectedResponse = new Response(201, [], '{"created": true}');
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('authenticate')
            ->once()
            ->with($request)
            ->andReturn($modifiedRequest);
        
        $middleware = new AuthenticationMiddleware($authenticator);
        
        $nextCalled = false;
        $next = function ($passedRequest) use ($modifiedRequest, $expectedResponse, &$nextCalled) {
            $nextCalled = true;
            $this->assertSame($modifiedRequest, $passedRequest);
            $this->assertTrue($passedRequest->hasHeader('X-API-Key'));
            return $expectedResponse;
        };
        
        $result = $middleware->process($request, $next);
        
        $this->assertTrue($nextCalled);
        $this->assertSame($expectedResponse, $result);
    }
}