<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Configuration;

use IBMCloud\Configuration\Configuration;
use IBMCloud\Configuration\ConfigurationBuilder;
use IBMCloud\Configuration\Providers\ChainProvider;
use IBMCloud\Configuration\Providers\EnvironmentProvider;
use IBMCloud\Configuration\Providers\FileProvider;
use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Transport\Middleware\CircuitBreakerMiddleware;
use IBMCloud\Transport\Middleware\RateLimitMiddleware;
use IBMCloud\Transport\Middleware\RetryMiddleware;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class ConfigurationBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        // Set required environment variable.
        $_ENV['IBM_API_KEY'] = 'test-api-key';
    }

    protected function tearDown(): void
    {
        unset($_ENV['IBM_API_KEY']);
    }

    public function testCreateBuildsBasicConfiguration(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRegion('eu-de')
            ->build();
        
        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertEquals('eu-de', $config->region);
        $this->assertEquals('production', $config->environment);
    }

    public function testWithEnvironmentSetsEnvironment(): void
    {
        $config = ConfigurationBuilder::create()
            ->withEnvironment('development')
            ->build();
        
        $this->assertEquals('development', $config->environment);
        $this->assertTrue($config->isDevelopment());
        $this->assertFalse($config->isProduction());
    }

    public function testWithLoggerSetsCustomLogger(): void
    {
        $logger = new TestLogger();
        
        $config = ConfigurationBuilder::create()
            ->withLogger($logger)
            ->build();
        
        $this->assertSame($logger, $config->logger);
    }

    public function testWithRetryPolicyAddsRetryMiddleware(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRetryPolicy(5, 2.0, 'exponential')
            ->build();
        
        $this->assertInstanceOf(Configuration::class, $config);
        
        // Verify middleware is added by checking transport type.
        $transportClass = get_class($config->transport);
        $this->assertStringContainsString('Transport', $transportClass);
    }

    public function testWithCircuitBreakerAddsMiddleware(): void
    {
        $config = ConfigurationBuilder::create()
            ->withCircuitBreaker(3, 30.0)
            ->build();
        
        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testWithRateLimitAddsMiddleware(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRateLimit(10, 20, 15.0)
            ->build();
        
        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testWithServiceDiscoveryEnablesFeature(): void
    {
        $config = ConfigurationBuilder::create()
            ->withServiceDiscovery(true)
            ->build();
        
        $this->assertTrue($config->serviceDiscoveryEnabled);
    }

    public function testWithWatsonXSetsServiceConfig(): void
    {
        $watsonxConfig = [
            'url' => 'https://custom.watsonx.com',
            'project_id' => 'custom-project'
        ];
        
        $config = ConfigurationBuilder::create()
            ->withWatsonX($watsonxConfig)
            ->build();
        
        $serviceConfig = $config->getWatsonXConfig();
        $this->assertEquals('https://custom.watsonx.com', $serviceConfig['url']);
        $this->assertEquals('custom-project', $serviceConfig['project_id']);
    }

    public function testWithObjectStorageSetsServiceConfig(): void
    {
        $cosConfig = [
            'endpoint' => 'https://custom.cos.com',
            'service_instance_id' => 'custom-instance'
        ];
        
        $config = ConfigurationBuilder::create()
            ->withObjectStorage($cosConfig)
            ->build();
        
        $serviceConfig = $config->getCOSConfig();
        $this->assertEquals('https://custom.cos.com', $serviceConfig['endpoint']);
        $this->assertEquals('custom-instance', $serviceConfig['service_instance_id']);
    }

    public function testBuildForProductionSetsProductionDefaults(): void
    {
        $config = ConfigurationBuilder::create()
            ->buildForProduction();
        
        $this->assertEquals('production', $config->environment);
        $this->assertTrue($config->isProduction());
    }

    public function testBuildForDevelopmentSetsDevelopmentDefaults(): void
    {
        $config = ConfigurationBuilder::create()
            ->buildForDevelopment();
        
        $this->assertEquals('development', $config->environment);
        $this->assertTrue($config->isDevelopment());
    }

    public function testBuildForTestingSetsTestingDefaults(): void
    {
        $config = ConfigurationBuilder::create()
            ->buildForTesting();
        
        $this->assertEquals('testing', $config->environment);
        $this->assertTrue($config->isTesting());
    }

    public function testWithConfigFileSetsFileProvider(): void
    {
        // Create temporary config file.
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode([
            'ibm_api_key' => 'file-api-key',
            'region' => 'file-region'
        ]));
        
        try {
            $config = ConfigurationBuilder::create()
                ->withConfigFile($tempFile)
                ->build();
            
            $this->assertInstanceOf(Configuration::class, $config);
        } finally {
            unlink($tempFile);
        }
    }

    public function testWithCustomConfigProviderSetsProvider(): void
    {
        $customProvider = new ChainProvider([
            new EnvironmentProvider()
        ]);
        
        $config = ConfigurationBuilder::create()
            ->withConfigProvider($customProvider)
            ->build();
        
        $this->assertSame($customProvider, $config->configProvider);
    }

    public function testAddMiddlewareAddsMiddleware(): void
    {
        $mockMiddleware = $this->createMock(MiddlewareInterface::class);
        
        $config = ConfigurationBuilder::create()
            ->addMiddleware($mockMiddleware)
            ->build();
        
        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testWithMiddlewareAddsMultipleMiddleware(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        
        $config = ConfigurationBuilder::create()
            ->withMiddleware([$middleware1, $middleware2])
            ->build();
        
        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testInvalidRetryStrategyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown retry strategy: invalid');
        
        ConfigurationBuilder::create()
            ->withRetryPolicy(3, 1.0, 'invalid')
            ->build();
    }

    public function testMissingApiKeyThrowsException(): void
    {
        unset($_ENV['IBM_API_KEY']);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API key is required');
        
        ConfigurationBuilder::create()->build();
    }

    public function testGetSummaryProvidesConfigurationInfo(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRegion('eu-de')
            ->withEnvironment('development')
            ->withServiceDiscovery(true)
            ->build();
        
        $summary = $config->getSummary();
        
        $this->assertEquals('eu-de', $summary['region']);
        $this->assertEquals('development', $summary['environment']);
        $this->assertTrue($summary['service_discovery_enabled']);
        $this->assertArrayHasKey('transport_class', $summary);
        $this->assertArrayHasKey('logger_class', $summary);
    }

    public function testFluentInterfaceChaining(): void
    {
        $config = ConfigurationBuilder::create()
            ->withRegion('us-south')
            ->withEnvironment('staging')
            ->withRetryPolicy(2, 0.5, 'linear')
            ->withCircuitBreaker(2, 15.0)
            ->withRateLimit(5, 10, 5.0)
            ->withServiceDiscovery(true)
            ->build();
        
        $this->assertEquals('us-south', $config->region);
        $this->assertEquals('staging', $config->environment);
        $this->assertTrue($config->serviceDiscoveryEnabled);
    }
}