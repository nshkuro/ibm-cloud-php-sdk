<?php

declare(strict_types=1);

namespace IBMCloud\Configuration\Providers;

/**
 * Configuration provider that chains multiple providers in order of priority.
 */
final class ChainProvider
{
    /** @var array<EnvironmentProvider|FileProvider> */
    private array $providers = [];

    /**
     * @param array<EnvironmentProvider|FileProvider> $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * Add a provider to the chain.
     */
    public function addProvider(EnvironmentProvider|FileProvider $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Get configuration value from the first provider that has it.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($key)) {
                return $provider->get($key, $default);
            }
        }

        return $default;
    }

    /**
     * Check if any provider has the configuration key.
     */
    public function has(string $key): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get merged configuration from all providers.
     */
    public function getConfiguration(): array
    {
        $config = [];

        // Merge configurations in reverse order so first provider has priority.
        foreach (array_reverse($this->providers) as $provider) {
            $config = array_merge($config, $provider->getConfiguration());
        }

        return $config;
    }

    /**
     * Get WatsonX configuration from first available provider.
     */
    public function getWatsonXConfig(): array
    {
        foreach ($this->providers as $provider) {
            $config = $provider->getWatsonXConfig();
            
            // Check if provider has at least one WatsonX config value.
            if (!empty(array_filter($config))) {
                return $config;
            }
        }

        return [
            'url' => 'https://us-south.ml.cloud.ibm.com',
            'project_id' => null,
            'document_connection_id' => null,
            'results_connection_id' => null,
        ];
    }

    /**
     * Get Object Storage configuration from first available provider.
     */
    public function getCOSConfig(): array
    {
        foreach ($this->providers as $provider) {
            $config = $provider->getCOSConfig();
            
            // Check if provider has at least one COS config value.
            if (!empty(array_filter($config))) {
                return $config;
            }
        }

        return [
            'endpoint' => 'https://s3.us-south.cloud-object-storage.appdomain.cloud',
            'service_instance_id' => null,
        ];
    }

    /**
     * Get authentication configuration.
     */
    public function getAuthConfig(): array
    {
        $config = [
            'api_key' => null,
            'region' => 'us-south',
        ];

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'getApiKey')) {
                $apiKey = $provider->getApiKey();
                if ($apiKey !== null) {
                    $config['api_key'] = $apiKey->getValue();
                    break;
                }
            }

            if (method_exists($provider, 'getAuthConfig')) {
                $authConfig = $provider->getAuthConfig();
                if (!empty(array_filter($authConfig))) {
                    $config = array_merge($config, $authConfig);
                    break;
                }
            }
        }

        return $config;
    }

    /**
     * Get logging configuration from first available provider.
     */
    public function getLoggingConfig(): array
    {
        foreach ($this->providers as $provider) {
            $config = $provider->getLoggingConfig();
            
            // Check if provider has at least one logging config value.
            if (!empty(array_filter($config))) {
                return $config;
            }
        }

        return [
            'level' => 'INFO',
            'enable_request_logging' => false,
        ];
    }

    /**
     * Create chain with common provider order.
     */
    public static function defaultChain(): self
    {
        return new self([
            new EnvironmentProvider(), // Highest priority.
            // FileProvider would be added by user if needed.
        ]);
    }

    /**
     * Create chain with environment and file providers.
     */
    public static function withFile(string $filePath): self
    {
        return new self([
            new EnvironmentProvider(), // Environment variables have priority.
            new FileProvider($filePath), // File as fallback.
        ]);
    }

    /**
     * Get all providers in the chain.
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get number of providers in the chain.
     */
    public function count(): int
    {
        return count($this->providers);
    }
}