<?php

declare(strict_types=1);

namespace IBMCloud\Authentication\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Immutable value object representing an authentication token.
 */
final class Token
{
    public function __construct(
        public string $value,
        public DateTimeInterface $expiresAt,
        public string $tokenType = 'Bearer',
        public ?string $refreshToken = null
    ) {
        if (empty($this->value)) {
            throw new \InvalidArgumentException('Token value cannot be empty.');
        }
    }

    /**
     * Create token from IBM Cloud IAM response.
     */
    public static function fromIamResponse(array $response): self
    {
        if (!isset($response['access_token'], $response['expires_in'])) {
            throw new \InvalidArgumentException('Invalid IAM response format.');
        }

        $expiresAt = (new DateTimeImmutable())
            ->modify(sprintf('+%d seconds', $response['expires_in']));

        return new self(
            value: $response['access_token'],
            expiresAt: $expiresAt,
            tokenType: $response['token_type'] ?? 'Bearer',
            refreshToken: $response['refresh_token'] ?? null
        );
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable();
    }

    /**
     * Check if token will expire within given seconds.
     */
    public function willExpireWithin(int $seconds): bool
    {
        $threshold = (new DateTimeImmutable())->modify(sprintf('+%d seconds', $seconds));
        
        return $this->expiresAt <= $threshold;
    }

    /**
     * Get Authorization header value.
     */
    public function toAuthorizationHeader(): string
    {
        return sprintf('%s %s', $this->tokenType, $this->value);
    }

    /**
     * Get seconds until expiration.
     */
    public function getSecondsUntilExpiration(): int
    {
        $now = new DateTimeImmutable();
        
        if ($this->expiresAt <= $now) {
            return 0;
        }
        
        return $this->expiresAt->getTimestamp() - $now->getTimestamp();
    }
}