<?php

declare(strict_types=1);

namespace IBMCloud\Configuration;

use IBMCloud\Configuration\Providers\ChainProvider;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Services\AI\WatsonX\Client as WatsonXClient;
use IBMCloud\Services\ObjectStorage\Client as COSClient;
use Psr\Log\LoggerInterface;

/**
 * Immutable configuration object for IBM Cloud SDK.
 */
final class Configuration
{
    public function __construct(
        public TransportInterface $transport,
        public string $region,
        public string $environment,
        public LoggerInterface $logger,
        public array $serviceConfigs,
        public ChainProvider $configProvider,
        public bool $serviceDiscoveryEnabled
    ) {}

    /**
     * Create WatsonX client.
     */
    public function createWatsonXClient(): WatsonXClient
    {
        $config = $this->serviceConfigs['watsonx'] ?? $this->configProvider->getWatsonXConfig();
        
        $endpoint = $config['url'] ?? $this->getRegionalEndpoint('watsonx');
        
        return new WatsonXClient($this->transport, $endpoint);
    }

    /**
     * Create Object Storage client.
     */
    public function createCOSClient(): COSClient
    {
        $config = $this->serviceConfigs['cos'] ?? $this->configProvider->getCOSConfig();
        
        $endpoint = $config['endpoint'] ?? $this->getRegionalEndpoint('cos');
        $serviceInstanceId = $config['service_instance_id'] ?? '';
        
        return new COSClient($this->transport, $endpoint, $serviceInstanceId);
    }

    /**
     * Get service configuration.
     */
    public function getServiceConfig(string $service): array
    {
        return $this->serviceConfigs[$service] ?? [];
    }

    /**
     * Get all service configurations.
     */
    public function getAllServiceConfigs(): array
    {
        return $this->serviceConfigs;
    }

    /**
     * Get WatsonX configuration.
     */
    public function getWatsonXConfig(): array
    {
        return $this->serviceConfigs['watsonx'] ?? $this->configProvider->getWatsonXConfig();
    }

    /**
     * Get Object Storage configuration.
     */
    public function getCOSConfig(): array
    {
        return $this->serviceConfigs['cos'] ?? $this->configProvider->getCOSConfig();
    }

    /**
     * Get logging configuration.
     */
    public function getLoggingConfig(): array
    {
        return $this->configProvider->getLoggingConfig();
    }

    /**
     * Check if development environment.
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }

    /**
     * Check if production environment.
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Check if testing environment.
     */
    public function isTesting(): bool
    {
        return $this->environment === 'testing';
    }

    /**
     * Get regional endpoint for service.
     */
    private function getRegionalEndpoint(string $service): string
    {
        $endpoints = [
            'watsonx' => [
                'us-south' => 'https://us-south.ml.cloud.ibm.com',
                'eu-gb' => 'https://eu-gb.ml.cloud.ibm.com',
                'eu-de' => 'https://eu-de.ml.cloud.ibm.com',
                'jp-tok' => 'https://jp-tok.ml.cloud.ibm.com',
            ],
            'cos' => [
                'us-south' => 'https://s3.us-south.cloud-object-storage.appdomain.cloud',
                'us-east' => 'https://s3.us-east.cloud-object-storage.appdomain.cloud',
                'eu-gb' => 'https://s3.eu-gb.cloud-object-storage.appdomain.cloud',
                'eu-de' => 'https://s3.eu-de.cloud-object-storage.appdomain.cloud',
                'ap-south' => 'https://s3.ap-south.cloud-object-storage.appdomain.cloud',
                'jp-tok' => 'https://s3.jp-tok.cloud-object-storage.appdomain.cloud',
            ],
        ];

        $serviceEndpoints = $endpoints[$service] ?? [];
        
        return $serviceEndpoints[$this->region] ?? $serviceEndpoints['us-south'] ?? '';
    }

    /**
     * Create a copy with different region.
     */
    public function withRegion(string $region): self
    {
        return new self(
            transport: $this->transport,
            region: $region,
            environment: $this->environment,
            logger: $this->logger,
            serviceConfigs: $this->serviceConfigs,
            configProvider: $this->configProvider,
            serviceDiscoveryEnabled: $this->serviceDiscoveryEnabled
        );
    }

    /**
     * Create a copy with different environment.
     */
    public function withEnvironment(string $environment): self
    {
        return new self(
            transport: $this->transport,
            region: $this->region,
            environment: $environment,
            logger: $this->logger,
            serviceConfigs: $this->serviceConfigs,
            configProvider: $this->configProvider,
            serviceDiscoveryEnabled: $this->serviceDiscoveryEnabled
        );
    }

    /**
     * Get configuration summary for debugging.
     */
    public function getSummary(): array
    {
        return [
            'region' => $this->region,
            'environment' => $this->environment,
            'service_discovery_enabled' => $this->serviceDiscoveryEnabled,
            'configured_services' => array_keys($this->serviceConfigs),
            'transport_class' => get_class($this->transport),
            'logger_class' => get_class($this->logger),
            'config_provider_count' => $this->configProvider->count(),
        ];
    }
}