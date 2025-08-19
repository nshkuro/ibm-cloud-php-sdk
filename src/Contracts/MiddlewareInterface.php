<?php

declare(strict_types=1);

namespace IBMCloud\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware interface for request/response processing pipeline.
 */
interface MiddlewareInterface
{
    /**
     * Process the request and optionally modify it.
     * 
     * @param RequestInterface $request The HTTP request
     * @param callable $next The next handler in the pipeline
     * @return ResponseInterface The HTTP response
     */
    public function process(RequestInterface $request, callable $next): ResponseInterface;
}