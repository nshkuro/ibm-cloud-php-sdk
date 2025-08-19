<?php

declare(strict_types=1);

namespace IBMCloud\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Exceptions\Transport\NetworkException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP transport implementation using Guzzle with middleware support.
 */
final class HttpTransport implements TransportInterface
{
    private ClientInterface $httpClient;
    
    /** @var MiddlewareInterface[] */
    private array $middleware = [];
    
    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct(?ClientInterface $httpClient = null, array $config = [])
    {
        $this->httpClient = $httpClient ?? new Client();
        $this->config = $config;
    }

    public function createRequest(string $method, string $uri, array $headers = [], mixed $body = null): RequestInterface
    {
        return new Request($method, $uri, $headers, $body);
    }

    public function send(RequestInterface $request): ResponseInterface
    {
        try {
            // Build middleware pipeline.
            $handler = $this->buildPipeline($request);
            
            // Execute the pipeline.
            return $handler();
        } catch (GuzzleException $e) {
            throw new NetworkException(
                sprintf('HTTP request failed: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $instance = clone $this;
        $instance->middleware[] = $middleware;
        
        return $instance;
    }

    public function withConfig(array $config): self
    {
        $instance = clone $this;
        $instance->config = array_merge($this->config, $config);
        
        return $instance;
    }

    /**
     * Build the middleware pipeline as a callable chain.
     */
    private function buildPipeline(RequestInterface $request): callable
    {
        // Start with the final handler that actually sends the request.
        $handler = fn(RequestInterface $req): ResponseInterface => $this->httpClient->sendRequest($req);
        
        // Wrap each middleware around the handler (reverse order so first added executes first).
        $middlewares = array_reverse($this->middleware);
        
        foreach ($middlewares as $middleware) {
            $currentHandler = $handler;
            $handler = fn(RequestInterface $req): ResponseInterface => $middleware->process($req, $currentHandler);
        }
        
        // Return a callable that starts the pipeline with the original request.
        return fn(): ResponseInterface => $handler($request);
    }
}