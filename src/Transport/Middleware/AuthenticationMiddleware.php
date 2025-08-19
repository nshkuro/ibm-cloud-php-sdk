<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware;

use IBMCloud\Contracts\AuthenticatorInterface;
use IBMCloud\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware that adds authentication to requests.
 */
final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthenticatorInterface $authenticator
    ) {
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        // Add authentication to the request.
        $authenticatedRequest = $this->authenticator->authenticate($request);
        
        // Continue with the authenticated request.
        return $next($authenticatedRequest);
    }
}