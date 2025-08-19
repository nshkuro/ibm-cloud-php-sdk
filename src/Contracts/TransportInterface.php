<?php

declare(strict_types=1);

namespace IBMCloud\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Transport interface for making HTTP requests to IBM Cloud services.
 */
interface TransportInterface
{
    /**
     * Create an HTTP request.
     */
    public function createRequest(string $method, string $uri, array $headers = [], mixed $body = null): RequestInterface;

    /**
     * Send an HTTP request.
     */
    public function send(RequestInterface $request): ResponseInterface;

    /**
     * Add middleware to the transport pipeline.
     */
    public function withMiddleware(MiddlewareInterface $middleware): self;

    /**
     * Set configuration for this transport instance.
     */
    public function withConfig(array $config): self;
}