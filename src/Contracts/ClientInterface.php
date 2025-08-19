<?php

declare(strict_types=1);

namespace IBMCloud\Contracts;

/**
 * Base interface for all IBM Cloud service clients.
 */
interface ClientInterface
{
    /**
     * Get the service name.
     */
    public function getServiceName(): string;

    /**
     * Get the service version.
     */
    public function getServiceVersion(): string;

    /**
     * Get the service endpoint URL.
     */
    public function getServiceUrl(): string;

    /**
     * Set the service endpoint URL.
     */
    public function setServiceUrl(string $url): void;
}