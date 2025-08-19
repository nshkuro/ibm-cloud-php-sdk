<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use IBMCloud\Contracts\ClientInterface;
use IBMCloud\Contracts\Services\ObjectStorageInterface;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Services\ObjectStorage\Models\Commands\BatchCommand;
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\Models\Results\BatchResult;
use IBMCloud\Services\ObjectStorage\Models\Results\ObjectResult;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use Psr\Http\Message\StreamInterface;

/**
 * IBM Cloud Object Storage client implementation.
 */
final class Client implements ObjectStorageInterface, ClientInterface
{
    private string $serviceUrl;

    public function __construct(
        private readonly TransportInterface $transport,
        ?string $serviceUrl = null,
        private readonly string $serviceInstanceId = ''
    ) {
        $this->serviceUrl = $serviceUrl ?? 'https://s3.us-south.cloud-object-storage.appdomain.cloud';
    }

    public function getServiceName(): string
    {
        return 'cloud-object-storage';
    }

    public function getServiceVersion(): string
    {
        return 'v1';
    }

    public function getServiceUrl(): string
    {
        return $this->serviceUrl;
    }

    public function setServiceUrl(string $url): void
    {
        $this->serviceUrl = rtrim($url, '/');
    }

    public function store(ObjectCommand $command): ObjectResult
    {
        if (!$command->isStore()) {
            throw new \InvalidArgumentException('Command must be a store operation.');
        }

        $url = sprintf('%s/%s/%s', 
            $this->serviceUrl, 
            $command->bucket->toString(), 
            urlencode($command->key->toString())
        );

        $headers = [
            'Content-Type' => $command->contentType ?? 'application/octet-stream',
        ];

        if ($command->storageClass !== null) {
            $headers['x-amz-storage-class'] = $command->storageClass->value;
        }

        // Add custom metadata with x-amz-meta- prefix.
        foreach ($command->metadata as $key => $value) {
            $headers['x-amz-meta-' . $key] = $value;
        }

        $request = new Request('PUT', $url, $headers, $command->content);

        $response = $this->transport->send($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('Failed to store object: HTTP %d', $response->getStatusCode())
            );
        }

        $etag = $response->getHeaderLine('ETag');
        
        return ObjectResult::stored(
            bucket: $command->bucket,
            key: $command->key,
            etag: trim($etag, '"'),
            storageClass: $command->storageClass
        );
    }

    public function retrieve(ObjectQuery $query): ObjectResult
    {
        $url = sprintf('%s/%s/%s', 
            $this->serviceUrl, 
            $query->bucket->toString(), 
            urlencode($query->key->toString())
        );

        $headers = [];
        
        if ($query->hasRange()) {
            $headers['Range'] = $query->getRangeHeader();
        }

        $request = new Request('GET', $url, $headers);

        $response = $this->transport->send($request);

        if ($response->getStatusCode() === 404) {
            throw new \RuntimeException('Object not found.');
        }

        if (!in_array($response->getStatusCode(), [200, 206])) {
            throw new \RuntimeException(
                sprintf('Failed to retrieve object: HTTP %d', $response->getStatusCode())
            );
        }

        $content = $response->getBody()->getContents();
        $contentType = $response->getHeaderLine('Content-Type');
        $etag = trim($response->getHeaderLine('ETag'), '"');
        $lastModified = $response->getHeaderLine('Last-Modified');

        // Extract custom metadata.
        $metadata = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (str_starts_with(strtolower($name), 'x-amz-meta-')) {
                $metaKey = substr($name, 11); // Remove 'x-amz-meta-' prefix
                $metadata[$metaKey] = $values[0] ?? '';
            }
        }

        return ObjectResult::retrieved(
            bucket: $query->bucket,
            key: $query->key,
            content: $content,
            contentType: $contentType ?: null,
            etag: $etag ?: null,
            lastModified: $lastModified ? new \DateTimeImmutable($lastModified) : null,
            metadata: $metadata
        );
    }

    public function delete(ObjectCommand $command): void
    {
        if (!$command->isDelete()) {
            throw new \InvalidArgumentException('Command must be a delete operation.');
        }

        $url = sprintf('%s/%s/%s', 
            $this->serviceUrl, 
            $command->bucket->toString(), 
            urlencode($command->key->toString())
        );

        $request = new Request('DELETE', $url);

        $response = $this->transport->send($request);

        if (!in_array($response->getStatusCode(), [204, 404])) {
            throw new \RuntimeException(
                sprintf('Failed to delete object: HTTP %d', $response->getStatusCode())
            );
        }
    }

    public function exists(ObjectQuery $query): bool
    {
        $url = sprintf('%s/%s/%s', 
            $this->serviceUrl, 
            $query->bucket->toString(), 
            urlencode($query->key->toString())
        );

        $request = new Request('HEAD', $url);

        try {
            $response = $this->transport->send($request);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function stream(ObjectQuery $query): StreamInterface
    {
        $url = sprintf('%s/%s/%s', 
            $this->serviceUrl, 
            $query->bucket->toString(), 
            urlencode($query->key->toString())
        );

        $headers = [];
        
        if ($query->hasRange()) {
            $headers['Range'] = $query->getRangeHeader();
        }

        $request = new Request('GET', $url, $headers);

        $response = $this->transport->send($request);

        if ($response->getStatusCode() === 404) {
            throw new \RuntimeException('Object not found.');
        }

        if (!in_array($response->getStatusCode(), [200, 206])) {
            throw new \RuntimeException(
                sprintf('Failed to stream object: HTTP %d', $response->getStatusCode())
            );
        }

        return $response->getBody();
    }

    public function batch(BatchCommand $commands): BatchResult
    {
        $successful = [];
        $failed = [];

        foreach ($commands->commands as $command) {
            $identifier = sprintf('%s:%s', 
                $command->bucket->toString(), 
                $command->key->toString()
            );

            try {
                if ($command->isStore()) {
                    $result = $this->store($command);
                    $successful[] = $result;
                } elseif ($command->isDelete()) {
                    $this->delete($command);
                    // Create a simple result for successful delete.
                    $successful[] = new ObjectResult($command->bucket, $command->key);
                }
            } catch (\Throwable $e) {
                $failed[$identifier] = $e;
            }
        }

        return new BatchResult($successful, $failed);
    }
}