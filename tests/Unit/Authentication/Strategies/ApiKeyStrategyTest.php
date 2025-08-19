<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Authentication\Strategies;

use GuzzleHttp\Psr7\Request;
use IBMCloud\Authentication\Strategies\ApiKeyStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use PHPUnit\Framework\TestCase;

class ApiKeyStrategyTest extends TestCase
{
    public function testAuthenticate(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $strategy = new ApiKeyStrategy($apiKey);
        
        $request = new Request('GET', 'https://api.example.com');
        $authenticatedRequest = $strategy->authenticate($request);
        
        $this->assertTrue($authenticatedRequest->hasHeader('X-API-Key'));
        $this->assertSame(['test-api-key-1234567890123456'], 
                         $authenticatedRequest->getHeader('X-API-Key'));
    }

    public function testAuthenticateWithCustomHeader(): void
    {
        $apiKey = new ApiKey('custom-header-key-1234567890123456');
        $strategy = new ApiKeyStrategy($apiKey, 'Authorization');
        
        $request = new Request('GET', 'https://api.example.com');
        $authenticatedRequest = $strategy->authenticate($request);
        
        $this->assertTrue($authenticatedRequest->hasHeader('Authorization'));
        $this->assertSame(['custom-header-key-1234567890123456'], 
                         $authenticatedRequest->getHeader('Authorization'));
    }

    public function testIsExpiredAlwaysReturnsFalse(): void
    {
        $apiKey = new ApiKey('never-expires-key-1234567890123456');
        $strategy = new ApiKeyStrategy($apiKey);
        
        $this->assertFalse($strategy->isExpired());
        
        // Should still be false after some time.
        $this->assertFalse($strategy->isExpired());
    }

    public function testRefreshIsNoOp(): void
    {
        $apiKey = new ApiKey('no-refresh-key-1234567890123456');
        $strategy = new ApiKeyStrategy($apiKey);
        
        // This should not throw any exceptions.
        $strategy->refresh();
        
        $this->assertTrue(true); // Test passed if no exception thrown
    }

    public function testGetToken(): void
    {
        $apiKey = new ApiKey('get-token-key-1234567890123456');
        $strategy = new ApiKeyStrategy($apiKey);
        
        $token = $strategy->getToken();
        
        $this->assertSame('get-token-key-1234567890123456', $token->value);
        $this->assertSame('ApiKey', $token->tokenType);
        $this->assertFalse($token->isExpired());
        
        // Token should be the same instance (cached).
        $token2 = $strategy->getToken();
        $this->assertSame($token, $token2);
    }

    public function testTokenHasLongExpiry(): void
    {
        $apiKey = new ApiKey('long-expiry-key-1234567890123456');
        $strategy = new ApiKeyStrategy($apiKey);
        
        $token = $strategy->getToken();
        
        // Should have many years left.
        $this->assertGreaterThan(365 * 24 * 3600, $token->getSecondsUntilExpiration());
        $this->assertFalse($token->willExpireWithin(365 * 24 * 3600)); // Won't expire within a year
    }
}