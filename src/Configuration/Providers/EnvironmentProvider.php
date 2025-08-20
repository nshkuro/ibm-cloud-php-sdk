<?php

declare(strict_types=1);

namespace IBMCloud\Configuration\Providers;

use IBMCloud\Authentication\ValueObjects\ApiKey;

/**
 * Configuration provider that reads from environment variables.
 */
final class EnvironmentProvider
{
    private array $envMapping = [
        'api_key' => 'IBM_API_KEY',
        'watsonx_url' => 'IBM_WATSONX_URL',
        'watsonx_project_id' => 'IBM_WATSONX_PROJECT_ID',
        'cos_endpoint' => 'IBM_COS_ENDPOINT',
        'cos_service_instance_id' => 'IBM_COS_SERVICE_INSTANCE_ID',
        'document_connection_id' => 'IBM_DOCUMENT_CONNECTION_ID',
        'results_connection_id' => 'IBM_RESULTS_CONNECTION_ID',
        'region' => 'IBM_REGION',
        'log_level' => 'LOG_LEVEL',
        'enable_request_logging' => 'ENABLE_REQUEST_LOGGING',
        'test_bucket_name' => 'TEST_BUCKET_NAME',
    ];

    /**
     * Get configuration from environment variables.
     */
    public function getConfiguration(): array
    {
        $config = [];

        foreach ($this->envMapping as $configKey => $envVar) {
            $value = getenv($envVar) ?: $_ENV[$envVar] ?? null;
            
            if ($value !== null) {
                $config[$configKey] = $this->castValue($configKey, $value);
            }
        }

        return $config;
    }

    /**
     * Get specific configuration value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $envVar = $this->envMapping[$key] ?? null;
        
        if ($envVar === null) {
            return $default;
        }

        $value = getenv($envVar) ?: $_ENV[$envVar] ?? null;
        
        if ($value === null) {
            return $default;
        }

        return $this->castValue($key, $value);
    }

    /**
     * Check if configuration key exists.
     */
    public function has(string $key): bool
    {
        $envVar = $this->envMapping[$key] ?? null;
        
        if ($envVar === null) {
            return false;
        }

        $value = getenv($envVar) ?: $_ENV[$envVar] ?? null;
        return $value !== null;
    }

    /**
     * Get API key from environment.
     */
    public function getApiKey(): ?ApiKey
    {
        $apiKeyValue = $this->get('api_key');
        
        if ($apiKeyValue === null) {
            return null;
        }

        return new ApiKey($apiKeyValue);
    }

    /**
     * Get WatsonX configuration.
     */
    public function getWatsonXConfig(): array
    {
        return [
            'url' => $this->get('watsonx_url', 'https://us-south.ml.cloud.ibm.com'),
            'project_id' => $this->get('watsonx_project_id'),
            'document_connection_id' => $this->get('document_connection_id'),
            'results_connection_id' => $this->get('results_connection_id'),
        ];
    }

    /**
     * Get Object Storage configuration.
     */
    public function getCOSConfig(): array
    {
        return [
            'endpoint' => $this->get('cos_endpoint', 'https://s3.us-south.cloud-object-storage.appdomain.cloud'),
            'service_instance_id' => $this->get('cos_service_instance_id'),
        ];
    }

    /**
     * Get logging configuration.
     */
    public function getLoggingConfig(): array
    {
        return [
            'level' => $this->get('log_level', 'INFO'),
            'enable_request_logging' => $this->get('enable_request_logging', false),
        ];
    }

    /**
     * Cast string value to appropriate type.
     */
    private function castValue(string $key, string $value): mixed
    {
        return match ($key) {
            'enable_request_logging' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'watsonx_project_id', 'cos_service_instance_id', 'document_connection_id', 'results_connection_id' => $value,
            default => $value
        };
    }

    /**
     * Add custom environment variable mapping.
     */
    public function addMapping(string $configKey, string $envVar): self
    {
        $this->envMapping[$configKey] = $envVar;
        return $this;
    }

    /**
     * Get all environment variable mappings.
     */
    public function getMappings(): array
    {
        return $this->envMapping;
    }
}