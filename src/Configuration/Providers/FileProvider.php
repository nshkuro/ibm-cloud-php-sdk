<?php

declare(strict_types=1);

namespace IBMCloud\Configuration\Providers;

use InvalidArgumentException;
use RuntimeException;

/**
 * Configuration provider that reads from JSON or YAML files.
 */
final class FileProvider
{
    private array $config = [];

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Configuration file not found: $filePath");
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("Configuration file is not readable: $filePath");
        }

        $this->loadFile($filePath);
    }

    /**
     * Load configuration from file.
     */
    private function loadFile(string $filePath): void
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException("Failed to read configuration file: $filePath");
        }

        $this->config = match ($extension) {
            'json' => $this->parseJson($content, $filePath),
            'yml', 'yaml' => $this->parseYaml($content, $filePath),
            'php' => $this->parsePhp($filePath),
            default => throw new InvalidArgumentException("Unsupported configuration file format: $extension")
        };
    }

    /**
     * Parse JSON configuration.
     */
    private function parseJson(string $content, string $filePath): array
    {
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('Invalid JSON in configuration file %s: %s', $filePath, json_last_error_msg())
            );
        }

        return $config ?? [];
    }

    /**
     * Parse YAML configuration.
     */
    private function parseYaml(string $content, string $filePath): array
    {
        if (!function_exists('yaml_parse')) {
            throw new RuntimeException('YAML extension is required to parse YAML configuration files');
        }

        $config = yaml_parse($content);

        if ($config === false) {
            throw new RuntimeException("Failed to parse YAML configuration file: $filePath");
        }

        return $config ?? [];
    }

    /**
     * Parse PHP configuration.
     */
    private function parsePhp(string $filePath): array
    {
        $config = include $filePath;

        if (!is_array($config)) {
            throw new RuntimeException("PHP configuration file must return an array: $filePath");
        }

        return $config;
    }

    /**
     * Get configuration value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    /**
     * Check if configuration key exists.
     */
    public function has(string $key): bool
    {
        return $this->getNestedValue($this->config, $key) !== null;
    }

    /**
     * Get all configuration.
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Get nested configuration value using dot notation.
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Create provider from JSON file.
     */
    public static function fromJson(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * Create provider from YAML file.
     */
    public static function fromYaml(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * Create provider from PHP file.
     */
    public static function fromPhp(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * Get WatsonX configuration.
     */
    public function getWatsonXConfig(): array
    {
        return [
            'url' => $this->get('watsonx.url', 'https://us-south.ml.cloud.ibm.com'),
            'project_id' => $this->get('watsonx.project_id'),
            'document_connection_id' => $this->get('watsonx.document_connection_id'),
            'results_connection_id' => $this->get('watsonx.results_connection_id'),
        ];
    }

    /**
     * Get Object Storage configuration.
     */
    public function getCOSConfig(): array
    {
        return [
            'endpoint' => $this->get('cos.endpoint', 'https://s3.us-south.cloud-object-storage.appdomain.cloud'),
            'service_instance_id' => $this->get('cos.service_instance_id'),
        ];
    }

    /**
     * Get authentication configuration.
     */
    public function getAuthConfig(): array
    {
        return [
            'api_key' => $this->get('auth.api_key'),
            'region' => $this->get('auth.region', 'us-south'),
        ];
    }

    /**
     * Get logging configuration.
     */
    public function getLoggingConfig(): array
    {
        return [
            'level' => $this->get('logging.level', 'INFO'),
            'enable_request_logging' => $this->get('logging.enable_request_logging', false),
        ];
    }
}