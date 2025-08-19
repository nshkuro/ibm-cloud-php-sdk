<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Transport;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Transport\HttpTransport;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpTransportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSendRequestWithoutMiddleware(): void
    {
        $request = new Request('GET', 'https://example.com');
        $expectedResponse = new Response(200, [], 'success');
        
        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('sendRequest')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);
        
        $transport = new HttpTransport($httpClient);
        $response = $transport->send($request);
        
        $this->assertSame($expectedResponse, $response);
    }

    public function testSendRequestWithMiddleware(): void
    {
        $request = new Request('GET', 'https://example.com');
        $expectedResponse = new Response(200, [], 'success');
        
        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('sendRequest')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);
        
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $middleware->shouldReceive('process')
            ->once()
            ->with($request, Mockery::type('callable'))
            ->andReturnUsing(function ($request, $next) use ($expectedResponse) {
                $this->assertInstanceOf(RequestInterface::class, $request);
                $this->assertIsCallable($next);
                // Call the next handler to continue the pipeline.
                return $next($request);
            });
        
        $transport = new HttpTransport($httpClient);
        $transportWithMiddleware = $transport->withMiddleware($middleware);
        
        // Verify immutability.
        $this->assertNotSame($transport, $transportWithMiddleware);
        
        $response = $transportWithMiddleware->send($request);
        $this->assertSame($expectedResponse, $response);
    }

    public function testWithConfigReturnsNewInstance(): void
    {
        $transport = new HttpTransport();
        $newTransport = $transport->withConfig(['timeout' => 30]);
        
        $this->assertNotSame($transport, $newTransport);
    }

    public function testMultipleMiddlewareExecution(): void
    {
        $request = new Request('GET', 'https://example.com');
        $expectedResponse = new Response(200, [], 'success');
        
        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('sendRequest')
            ->once()
            ->andReturn($expectedResponse);
        
        $executionOrder = [];
        
        $middleware1 = Mockery::mock(MiddlewareInterface::class);
        $middleware1->shouldReceive('process')
            ->once()
            ->andReturnUsing(function ($request, $next) use (&$executionOrder, $expectedResponse) {
                $executionOrder[] = 'middleware1_before';
                $response = $next($request);
                $executionOrder[] = 'middleware1_after';
                return $response;
            });
        
        $middleware2 = Mockery::mock(MiddlewareInterface::class);
        $middleware2->shouldReceive('process')
            ->once()
            ->andReturnUsing(function ($request, $next) use (&$executionOrder, $expectedResponse) {
                $executionOrder[] = 'middleware2_before';
                $response = $next($request);
                $executionOrder[] = 'middleware2_after';
                return $response;
            });
        
        $transport = new HttpTransport($httpClient);
        $transport = $transport->withMiddleware($middleware1)
                              ->withMiddleware($middleware2);
        
        $response = $transport->send($request);
        
        // Verify middleware execution order (first added, first executed).
        $this->assertSame([
            'middleware1_before',
            'middleware2_before',
            'middleware2_after', 
            'middleware1_after'
        ], $executionOrder);
        
        $this->assertSame($expectedResponse, $response);
    }
}