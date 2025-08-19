<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Authentication\Strategies;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Exceptions\Authentication\AuthenticationException;
use Mockery;
use PHPUnit\Framework\TestCase;

class IamStrategyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAuthenticate(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        // Mock successful token response.
        $tokenResponse = new Response(200, [], json_encode([
            'access_token' => 'iam-access-token-123',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]));
        
        $transport->shouldReceive('send')
            ->once()
            ->andReturn($tokenResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        $request = new Request('GET', 'https://api.example.com');
        
        $authenticatedRequest = $strategy->authenticate($request);
        
        $this->assertTrue($authenticatedRequest->hasHeader('Authorization'));
        $this->assertSame(['Bearer iam-access-token-123'], 
                         $authenticatedRequest->getHeader('Authorization'));
    }

    public function testGetTokenCachesResult(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $tokenResponse = new Response(200, [], json_encode([
            'access_token' => 'cached-token-456',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]));
        
        // Should only be called once due to caching.
        $transport->shouldReceive('send')
            ->once()
            ->andReturn($tokenResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $token1 = $strategy->getToken();
        $token2 = $strategy->getToken();
        
        $this->assertSame('cached-token-456', $token1->value);
        $this->assertSame($token1, $token2); // Same instance due to caching
    }

    public function testRefreshTokenWhenExpired(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        // First response - short-lived token.
        $shortTokenResponse = new Response(200, [], json_encode([
            'access_token' => 'short-lived-token',
            'expires_in' => 1, // Expires in 1 second
            'token_type' => 'Bearer',
        ]));
        
        // Second response - new token after refresh.
        $newTokenResponse = new Response(200, [], json_encode([
            'access_token' => 'refreshed-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]));
        
        $transport->shouldReceive('send')
            ->twice()
            ->andReturn($shortTokenResponse, $newTokenResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        // Get initial token.
        $token1 = $strategy->getToken();
        $this->assertSame('short-lived-token', $token1->value);
        
        // Wait for expiration and get new token.
        sleep(2);
        $token2 = $strategy->getToken();
        $this->assertSame('refreshed-token', $token2->value);
    }

    public function testIsExpiredReturnsTrueForNoToken(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $this->assertTrue($strategy->isExpired());
    }

    public function testRefreshExplicitlyUpdatesToken(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $tokenResponse = new Response(200, [], json_encode([
            'access_token' => 'explicit-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]));
        
        $transport->shouldReceive('send')
            ->once()
            ->andReturn($tokenResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $this->assertTrue($strategy->isExpired());
        
        $strategy->refresh();
        
        $this->assertFalse($strategy->isExpired());
        $this->assertSame('explicit-refresh-token', $strategy->getToken()->value);
    }

    public function testThrowsExceptionOnTransportError(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $transport->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Network error'));
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to authenticate with IBM Cloud IAM: Network error');
        
        $strategy->getToken();
    }

    public function testThrowsExceptionOnNon200Response(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $errorResponse = new Response(401, [], json_encode([
            'error' => 'invalid_apikey',
            'error_description' => 'The provided API key is invalid'
        ]));
        
        $transport->shouldReceive('send')
            ->once()
            ->andReturn($errorResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('IBM Cloud IAM authentication failed with status 401');
        
        $strategy->getToken();
    }

    public function testThrowsExceptionOnInvalidJson(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $invalidResponse = new Response(200, [], 'invalid json');
        
        $transport->shouldReceive('send')
            ->once()
            ->andReturn($invalidResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid JSON response from IBM Cloud IAM.');
        
        $strategy->getToken();
    }

    public function testThrowsExceptionOnMissingFields(): void
    {
        $apiKey = new ApiKey('test-api-key-1234567890123456');
        $transport = Mockery::mock(TransportInterface::class);
        
        $incompleteResponse = new Response(200, [], json_encode([
            'access_token' => 'token-without-expiry'
            // Missing 'expires_in'
        ]));
        
        $transport->shouldReceive('send')
            ->once()
            ->andReturn($incompleteResponse);
        
        $strategy = new IamStrategy($apiKey, $transport);
        
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid IAM response: missing required fields.');
        
        $strategy->getToken();
    }
}