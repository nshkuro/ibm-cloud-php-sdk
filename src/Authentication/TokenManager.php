<?php

declare(strict_types=1);

namespace IBMCloud\Authentication;

use IBMCloud\Authentication\ValueObjects\Scope;
use IBMCloud\Authentication\ValueObjects\Token;
use IBMCloud\Contracts\AuthenticatorInterface;

/**
 * Manages authentication tokens with automatic refresh and caching.
 */
final class TokenManager
{
    /** @var array<string, Token> */
    private array $tokenCache = [];
    
    /** @var array<string, AuthenticatorInterface> */
    private array $authenticators = [];

    public function __construct(
        private readonly int $refreshThresholdSeconds = 300 // 5 minutes
    ) {
    }

    /**
     * Register an authenticator for a specific scope.
     */
    public function registerAuthenticator(Scope $scope, AuthenticatorInterface $authenticator): void
    {
        $this->authenticators[$scope->getIdentifier()] = $authenticator;
    }

    /**
     * Get a valid token for the given scope.
     */
    public function getToken(Scope $scope): Token
    {
        $identifier = $scope->getIdentifier();
        
        // Check if we have a cached token.
        if (isset($this->tokenCache[$identifier])) {
            $token = $this->tokenCache[$identifier];
            
            // Return if token is still valid and won't expire soon.
            if (!$token->willExpireWithin($this->refreshThresholdSeconds)) {
                return $token;
            }
        }
        
        // Need to refresh or get new token.
        $token = $this->refreshToken($scope);
        $this->tokenCache[$identifier] = $token;
        
        return $token;
    }

    /**
     * Manually invalidate a token for a scope.
     */
    public function invalidateToken(Scope $scope): void
    {
        unset($this->tokenCache[$scope->getIdentifier()]);
    }

    /**
     * Clear all cached tokens.
     */
    public function clearTokenCache(): void
    {
        $this->tokenCache = [];
    }

    /**
     * Check if we have a valid token for the scope.
     */
    public function hasValidToken(Scope $scope): bool
    {
        $identifier = $scope->getIdentifier();
        
        if (!isset($this->tokenCache[$identifier])) {
            return false;
        }
        
        $token = $this->tokenCache[$identifier];
        
        return !$token->willExpireWithin($this->refreshThresholdSeconds);
    }

    /**
     * Get all cached tokens (for debugging/monitoring).
     * 
     * @return array<string, array{scope: string, expires_in: int, expires_at: string}>
     */
    public function getTokenStatus(): array
    {
        $status = [];
        
        foreach ($this->tokenCache as $identifier => $token) {
            $status[$identifier] = [
                'scope' => $identifier,
                'expires_in' => $token->getSecondsUntilExpiration(),
                'expires_at' => $token->expiresAt->format('c'),
                'is_expired' => $token->isExpired(),
            ];
        }
        
        return $status;
    }

    /**
     * Refresh token using the appropriate authenticator.
     */
    private function refreshToken(Scope $scope): Token
    {
        $identifier = $scope->getIdentifier();
        
        // Look for exact match first.
        if (isset($this->authenticators[$identifier])) {
            $authenticator = $this->authenticators[$identifier];
        } else {
            // Fall back to global authenticator.
            $globalIdentifier = Scope::global()->getIdentifier();
            if (isset($this->authenticators[$globalIdentifier])) {
                $authenticator = $this->authenticators[$globalIdentifier];
            } else {
                throw new \RuntimeException(
                    sprintf('No authenticator registered for scope: %s', $identifier)
                );
            }
        }
        
        if ($authenticator->isExpired()) {
            $authenticator->refresh();
        }
        
        return $authenticator->getToken();
    }
}