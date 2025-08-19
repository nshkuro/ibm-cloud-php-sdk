<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Authentication\ValueObjects;

use DateTimeImmutable;
use IBMCloud\Authentication\ValueObjects\Token;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testCreateToken(): void
    {
        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new Token('test-token-value', $expiresAt);
        
        $this->assertSame('test-token-value', $token->value);
        $this->assertSame($expiresAt, $token->expiresAt);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertNull($token->refreshToken);
    }

    public function testCreateTokenWithCustomValues(): void
    {
        $expiresAt = new DateTimeImmutable('+1 hour');
        $token = new Token(
            value: 'custom-token',
            expiresAt: $expiresAt,
            tokenType: 'Custom',
            refreshToken: 'refresh-123'
        );
        
        $this->assertSame('custom-token', $token->value);
        $this->assertSame('Custom', $token->tokenType);
        $this->assertSame('refresh-123', $token->refreshToken);
    }

    public function testRejectsEmptyTokenValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token value cannot be empty.');
        
        new Token('', new DateTimeImmutable('+1 hour'));
    }

    public function testFromIamResponse(): void
    {
        $response = [
            'access_token' => 'iam-token-123',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'refresh_token' => 'refresh-456'
        ];
        
        $token = Token::fromIamResponse($response);
        
        $this->assertSame('iam-token-123', $token->value);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame('refresh-456', $token->refreshToken);
        $this->assertGreaterThan(3500, $token->getSecondsUntilExpiration());
    }

    public function testFromIamResponseWithMissingFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IAM response format.');
        
        Token::fromIamResponse(['access_token' => 'token']);
    }

    public function testIsExpired(): void
    {
        $expiredToken = new Token('expired', new DateTimeImmutable('-1 hour'));
        $validToken = new Token('valid', new DateTimeImmutable('+1 hour'));
        
        $this->assertTrue($expiredToken->isExpired());
        $this->assertFalse($validToken->isExpired());
    }

    public function testWillExpireWithin(): void
    {
        $token = new Token('test', new DateTimeImmutable('+5 minutes'));
        
        $this->assertTrue($token->willExpireWithin(600)); // 10 minutes
        $this->assertFalse($token->willExpireWithin(60)); // 1 minute
    }

    public function testToAuthorizationHeader(): void
    {
        $token = new Token('abc123', new DateTimeImmutable('+1 hour'));
        
        $this->assertSame('Bearer abc123', $token->toAuthorizationHeader());
    }

    public function testToAuthorizationHeaderWithCustomType(): void
    {
        $token = new Token('xyz789', new DateTimeImmutable('+1 hour'), 'Custom');
        
        $this->assertSame('Custom xyz789', $token->toAuthorizationHeader());
    }

    public function testGetSecondsUntilExpiration(): void
    {
        $expiredToken = new Token('expired', new DateTimeImmutable('-1 hour'));
        $futureToken = new Token('future', new DateTimeImmutable('+1 hour'));
        
        $this->assertSame(0, $expiredToken->getSecondsUntilExpiration());
        $this->assertGreaterThan(3500, $futureToken->getSecondsUntilExpiration());
        $this->assertLessThan(3700, $futureToken->getSecondsUntilExpiration());
    }
}