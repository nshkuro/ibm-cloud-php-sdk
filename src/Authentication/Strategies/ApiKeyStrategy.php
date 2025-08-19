<?php

declare(strict_types=1);

namespace IBMCloud\Authentication\Strategies;

use DateTimeImmutable;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Authentication\ValueObjects\Token;
use IBMCloud\Contracts\AuthenticatorInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Simple API key authentication strategy.
 * 
 * Directly uses the API key for authentication without token exchange.
 * Used for services that accept API keys directly.
 */
final class ApiKeyStrategy implements AuthenticatorInterface
{
    private readonly Token $staticToken;

    public function __construct(
        private readonly ApiKey $apiKey,
        private readonly string $headerName = 'X-API-Key'
    ) {
        // Create a static token that never expires for API key auth.
        $this->staticToken = new Token(
            value: $this->apiKey->value,
            expiresAt: new DateTimeImmutable('+10 years'), // Effectively never expires
            tokenType: 'ApiKey'
        );
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader($this->headerName, $this->apiKey->value);
    }

    public function isExpired(): bool
    {
        // API keys don't expire (unless the user revokes them externally).
        return false;
    }

    public function refresh(): void
    {
        // API keys don't need refresh - they're valid until revoked.
        // This is a no-op.
    }

    public function getToken(): Token
    {
        return $this->staticToken;
    }
}