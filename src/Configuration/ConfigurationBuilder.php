<?php

declare(strict_types=1);

namespace IBMCloud\Configuration;

use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Configuration\Providers\ChainProvider;
use IBMCloud\Configuration\Providers\EnvironmentProvider;
use IBMCloud\Configuration\Providers\FileProvider;
use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Transport\HttpTransport;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use IBMCloud\Transport\Middleware\CircuitBreakerMiddleware;
use IBMCloud\Transport\Middleware\LoggingMiddleware;
use IBMCloud\Transport\Middleware\RateLimitMiddleware;
use IBMCloud\Transport\Middleware\RetryMiddleware;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fluent configuration builder for IBM Cloud SDK.
 */
final class ConfigurationBuilder
{
    private ChainProvider $configProvider;
    private ?string $region = null;
    private ?string $environment = null;
    private array $middleware = [];
    private ?LoggerInterface $logger = null;
    private array $serviceConfigs = [];
    private bool $serviceDiscoveryEnabled = false;

    private function __construct()
    {
        $this->configProvider = ChainProvider::defaultChain();
        $this->logger = new NullLogger();
    }

    /**
     * Create new configuration builder.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set region for all services.
     */
    public function withRegion(string $region): self
    {
        $this->region = $region;
        return $this;
    }

    /**
     * Set environment (development, staging, production).
     */
    public function withEnvironment(string $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * Add file-based configuration.
     */
    public function withConfigFile(string $filePath): self
    {
        $this->configProvider->addProvider(new FileProvider($filePath));
        return $this;
    }

    /**
     * Set custom configuration provider.
     */
    public function withConfigProvider(ChainProvider $provider): self
    {
        $this->configProvider = $provider;
        return $this;
    }

    /**
     * Set logger for the SDK.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Enable service discovery.
     */
    public function withServiceDiscovery(bool $enabled = true): self
    {
        $this->serviceDiscoveryEnabled = $enabled;
        return $this;
    }

    /**
     * Add middleware to the transport pipeline.
     */
    public function withMiddleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Add single middleware.
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Configure retry policy.
     */
    public function withRetryPolicy(
        int $maxAttempts = 3,
        float $baseDelay = 1.0,
        string $strategy = 'exponential'
    ): self {
        $retryMiddleware = match ($strategy) {
            'exponential' => RetryMiddleware::exponentialBackoff($maxAttempts, $baseDelay, $this->logger),
            'linear' => RetryMiddleware::linearBackoff($maxAttempts, $baseDelay, $this->logger),
            'fixed' => RetryMiddleware::fixedDelay($maxAttempts, $baseDelay, $this->logger),
            default => throw new InvalidArgumentException("Unknown retry strategy: $strategy")
        };

        $this->middleware[] = $retryMiddleware;
        return $this;
    }

    /**
     * Configure circuit breaker.
     */
    public function withCircuitBreaker(
        int $failureThreshold = 5,
        float $timeout = 60.0
    ): self {
        $this->middleware[] = CircuitBreakerMiddleware::create($failureThreshold, $timeout, $this->logger);
        return $this;
    }

    /**
     * Configure rate limiting.
     */
    public function withRateLimit(
        int $requestsPerSecond,
        ?int $burstCapacity = null,
        float $maxWaitTime = 10.0
    ): self {
        $this->middleware[] = RateLimitMiddleware::perSecond(
            $requestsPerSecond,
            $burstCapacity,
            $maxWaitTime,
            $this->logger
        );
        return $this;
    }

    /**
     * Configure WatsonX service.
     */
    public function withWatsonX(array $config = []): self
    {
        $defaultConfig = $this->configProvider->getWatsonXConfig();
        $this->serviceConfigs['watsonx'] = array_merge($defaultConfig, $config);
        return $this;
    }

    /**
     * Configure Object Storage service.
     */
    public function withObjectStorage(array $config = []): self
    {
        $defaultConfig = $this->configProvider->getCOSConfig();
        $this->serviceConfigs['cos'] = array_merge($defaultConfig, $config);
        return $this;
    }

    /**
     * Build the configuration.
     */
    public function build(): Configuration
    {
        // Get authentication configuration.
        $authConfig = $this->configProvider->getAuthConfig();
        $apiKey = $authConfig['api_key'] ? new ApiKey($authConfig['api_key']) : null;

        if ($apiKey === null) {
            throw new InvalidArgumentException('API key is required. Set IBM_API_KEY environment variable or provide it in configuration file.');
        }

        // Create transport with middleware.
        $transport = new HttpTransport();
        
        // Add authentication middleware first.
        $iamStrategy = new IamStrategy($apiKey, $transport);
        $authenticatedTransport = $transport->withMiddleware(new AuthenticationMiddleware($iamStrategy));

        // Add logging middleware if request logging is enabled.
        $loggingConfig = $this->configProvider->getLoggingConfig();
        if ($loggingConfig['enable_request_logging']) {
            $authenticatedTransport = $authenticatedTransport->withMiddleware(new LoggingMiddleware($this->logger));
        }

        // Add custom middleware.
        foreach ($this->middleware as $middleware) {
            $authenticatedTransport = $authenticatedTransport->withMiddleware($middleware);
        }

        return new Configuration(
            transport: $authenticatedTransport,
            region: $this->region ?? $authConfig['region'] ?? 'us-south',
            environment: $this->environment ?? 'production',
            logger: $this->logger,
            serviceConfigs: $this->serviceConfigs,
            configProvider: $this->configProvider,
            serviceDiscoveryEnabled: $this->serviceDiscoveryEnabled
        );
    }

    /**
     * Build with recommended production settings.
     */
    public function buildForProduction(): Configuration
    {
        return $this
            ->withEnvironment('production')
            ->withRetryPolicy(3, 1.0, 'exponential')
            ->withCircuitBreaker(5, 60.0)
            ->withRateLimit(10, 20, 30.0)
            ->build();
    }

    /**
     * Build with development settings.
     */
    public function buildForDevelopment(): Configuration
    {
        return $this
            ->withEnvironment('development')
            ->withRetryPolicy(2, 0.5, 'linear')
            ->withRateLimit(5, 10, 5.0)
            ->build();
    }

    /**
     * Build minimal configuration for testing.
     */
    public function buildForTesting(): Configuration
    {
        return $this
            ->withEnvironment('testing')
            ->withRetryPolicy(1, 0.1, 'fixed')
            ->build();
    }
}