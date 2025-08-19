<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Authentication;

use DateTimeImmutable;
use IBMCloud\Authentication\TokenManager;
use IBMCloud\Authentication\ValueObjects\Scope;
use IBMCloud\Authentication\ValueObjects\Token;
use IBMCloud\Contracts\AuthenticatorInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

class TokenManagerTest extends TestCase
{
    private TokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenManager = new TokenManager(300); // 5 minutes threshold
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testRegisterAuthenticator(): void
    {
        $scope = Scope::watsonxAi();
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        
        $this->tokenManager->registerAuthenticator($scope, $authenticator);
        
        // No exception means success.
        $this->assertTrue(true);
    }

    public function testGetTokenWithFreshToken(): void
    {
        $scope = Scope::watsonxAi();
        $token = new Token('fresh-token', new DateTimeImmutable('+1 hour'));
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('isExpired')->once()->andReturn(false);
        $authenticator->shouldReceive('getToken')->once()->andReturn($token);
        
        $this->tokenManager->registerAuthenticator($scope, $authenticator);
        
        $result = $this->tokenManager->getToken($scope);
        
        $this->assertSame($token, $result);
    }

    public function testGetTokenUsesCache(): void
    {
        $scope = Scope::watsonxAi();
        $token = new Token('cached-token', new DateTimeImmutable('+1 hour'));
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('isExpired')->once()->andReturn(false);
        $authenticator->shouldReceive('getToken')->once()->andReturn($token);
        
        $this->tokenManager->registerAuthenticator($scope, $authenticator);
        
        // First call should fetch from authenticator.
        $result1 = $this->tokenManager->getToken($scope);
        
        // Second call should use cache (no additional calls to authenticator).
        $result2 = $this->tokenManager->getToken($scope);
        
        $this->assertSame($token, $result1);
        $this->assertSame($token, $result2);
    }

    public function testGetTokenRefreshesExpiredToken(): void
    {
        $scope = Scope::watsonxAi();
        $expiredToken = new Token('expired-token', new DateTimeImmutable('+1 minute'));
        $freshToken = new Token('fresh-token', new DateTimeImmutable('+1 hour'));
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        
        // First call - returns expired token.
        $authenticator->shouldReceive('isExpired')->once()->andReturn(false);
        $authenticator->shouldReceive('getToken')->once()->andReturn($expiredToken);
        
        // Second call - token needs refresh.
        $authenticator->shouldReceive('isExpired')->once()->andReturn(true);
        $authenticator->shouldReceive('refresh')->once();
        $authenticator->shouldReceive('getToken')->once()->andReturn($freshToken);
        
        $this->tokenManager->registerAuthenticator($scope, $authenticator);
        
        // First call.
        $result1 = $this->tokenManager->getToken($scope);
        $this->assertSame($expiredToken, $result1);
        
        // Second call should refresh.
        $result2 = $this->tokenManager->getToken($scope);
        $this->assertSame($freshToken, $result2);
    }

    public function testGetTokenWithoutAuthenticatorThrowsException(): void
    {
        $scope = Scope::watsonxAi();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No authenticator registered for scope: watsonx-ai:us-south');
        
        $this->tokenManager->getToken($scope);
    }

    public function testGetTokenFallsBackToGlobalAuthenticator(): void
    {
        $specificScope = Scope::watsonxAi();
        $globalScope = Scope::global();
        $token = new Token('global-token', new DateTimeImmutable('+1 hour'));
        
        $globalAuthenticator = Mockery::mock(AuthenticatorInterface::class);
        $globalAuthenticator->shouldReceive('isExpired')->once()->andReturn(false);
        $globalAuthenticator->shouldReceive('getToken')->once()->andReturn($token);
        
        // Only register global authenticator.
        $this->tokenManager->registerAuthenticator($globalScope, $globalAuthenticator);
        
        $result = $this->tokenManager->getToken($specificScope);
        
        $this->assertSame($token, $result);
    }

    public function testInvalidateToken(): void
    {
        $scope = Scope::watsonxAi();
        $token = new Token('token-to-invalidate', new DateTimeImmutable('+1 hour'));
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('isExpired')->twice()->andReturn(false);
        $authenticator->shouldReceive('getToken')->twice()->andReturn($token);
        
        $this->tokenManager->registerAuthenticator($scope, $authenticator);
        
        // Get token to cache it.
        $this->tokenManager->getToken($scope);
        $this->assertTrue($this->tokenManager->hasValidToken($scope));
        
        // Invalidate and verify.
        $this->tokenManager->invalidateToken($scope);
        $this->assertFalse($this->tokenManager->hasValidToken($scope));
        
        // Next call should fetch fresh token.
        $result = $this->tokenManager->getToken($scope);
        $this->assertSame($token, $result);
    }

    public function testClearTokenCache(): void
    {
        $scope1 = Scope::watsonxAi();
        $scope2 = Scope::objectStorage();
        $token = new Token('test-token', new DateTimeImmutable('+1 hour'));
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('isExpired')->times(2)->andReturn(false);
        $authenticator->shouldReceive('getToken')->times(2)->andReturn($token);
        
        $this->tokenManager->registerAuthenticator($scope1, $authenticator);
        $this->tokenManager->registerAuthenticator($scope2, $authenticator);
        
        // Cache tokens.
        $this->tokenManager->getToken($scope1);
        $this->tokenManager->getToken($scope2);
        
        $this->assertTrue($this->tokenManager->hasValidToken($scope1));
        $this->assertTrue($this->tokenManager->hasValidToken($scope2));
        
        // Clear cache.
        $this->tokenManager->clearTokenCache();
        
        $this->assertFalse($this->tokenManager->hasValidToken($scope1));
        $this->assertFalse($this->tokenManager->hasValidToken($scope2));
    }

    public function testGetTokenStatus(): void
    {
        $scope = Scope::watsonxAi();
        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new Token('status-token', $expiresAt);
        
        $authenticator = Mockery::mock(AuthenticatorInterface::class);
        $authenticator->shouldReceive('isExpired')->once()->andReturn(false);
        $authenticator->shouldReceive('getToken')->once()->andReturn($token);
        
        $this->tokenManager->registerAuthenticator($scope, $authenticator);
        $this->tokenManager->getToken($scope);
        
        $status = $this->tokenManager->getTokenStatus();
        
        $this->assertArrayHasKey('watsonx-ai:us-south', $status);
        
        $tokenStatus = $status['watsonx-ai:us-south'];
        $this->assertSame('watsonx-ai:us-south', $tokenStatus['scope']);
        $this->assertSame($expiresAt->format('c'), $tokenStatus['expires_at']);
        $this->assertFalse($tokenStatus['is_expired']);
        $this->assertGreaterThan(3500, $tokenStatus['expires_in']);
    }
}