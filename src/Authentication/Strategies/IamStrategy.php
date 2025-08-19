<?php

declare(strict_types=1);

namespace IBMCloud\Authentication\Strategies;

use GuzzleHttp\Psr7\Request;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Authentication\ValueObjects\Token;
use IBMCloud\Contracts\AuthenticatorInterface;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Exceptions\Authentication\AuthenticationException;
use Psr\Http\Message\RequestInterface;

/**
 * IBM Cloud IAM authentication strategy.
 * 
 * Exchanges API key for access tokens via IBM Cloud Identity and Access Management.
 */
final class IamStrategy implements AuthenticatorInterface
{
    private ?Token $currentToken = null;

    public function __construct(
        private readonly ApiKey $apiKey,
        private readonly TransportInterface $transport,
        private readonly string $iamUrl = 'https://iam.cloud.ibm.com'
    ) {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $token = $this->getToken();
        
        return $request->withHeader('Authorization', $token->toAuthorizationHeader());
    }

    public function isExpired(): bool
    {
        if ($this->currentToken === null) {
            return true;
        }
        
        // Consider token expired if it expires within 5 minutes.
        return $this->currentToken->willExpireWithin(300);
    }

    public function refresh(): void
    {
        $this->currentToken = $this->requestNewToken();
    }

    public function getToken(): Token
    {
        if ($this->currentToken === null || $this->isExpired()) {
            $this->refresh();
        }
        
        return $this->currentToken;
    }

    /**
     * Request a new token from IBM Cloud IAM.
     */
    private function requestNewToken(): Token
    {
        $request = new Request(
            'POST',
            $this->iamUrl . '/identity/token',
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            http_build_query([
                'grant_type' => 'urn:ibm:params:oauth:grant-type:apikey',
                'apikey' => $this->apiKey->value,
            ])
        );

        try {
            $response = $this->transport->send($request);
        } catch (\Throwable $e) {
            throw new AuthenticationException(
                'Failed to authenticate with IBM Cloud IAM: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new AuthenticationException(
                sprintf(
                    'IBM Cloud IAM authentication failed with status %d: %s',
                    $response->getStatusCode(),
                    $response->getBody()->getContents()
                )
            );
        }

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!is_array($data)) {
            throw new AuthenticationException('Invalid JSON response from IBM Cloud IAM.');
        }

        if (!isset($data['access_token'], $data['expires_in'])) {
            throw new AuthenticationException('Invalid IAM response: missing required fields.');
        }

        return Token::fromIamResponse($data);
    }
}