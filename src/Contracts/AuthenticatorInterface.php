<?php

declare(strict_types=1);

namespace IBMCloud\Contracts;

use Psr\Http\Message\RequestInterface;

/**
 * Interface for authentication strategies.
 */
interface AuthenticatorInterface
{
    /**
     * Authenticate a request by adding required headers.
     */
    public function authenticate(RequestInterface $request): RequestInterface;

    /**
     * Check if the current authentication is expired.
     */
    public function isExpired(): bool;

    /**
     * Refresh authentication if possible.
     */
    public function refresh(): void;
}