<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use IBMCloud\Authentication\Strategies\ApiKeyStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Services\ObjectStorage\Client;
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Services\ObjectStorage\ValueObjects\StorageClass;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use IBMCloud\Transport\Middleware\LoggingMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Load environment variables from .env file (optional for mock example)
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/**
 * Mock transport that simulates IBM COS API responses.
 */
class MockCosTransport implements TransportInterface
{
    private array $storedObjects = [];

    public function send(RequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $path = parse_url($uri, PHP_URL_PATH);
        
        echo "  → {$method} {$uri}\n";
        
        // Parse bucket and object key from path
        $pathParts = explode('/', trim($path, '/'));
        $bucket = $pathParts[0] ?? '';
        $objectKey = urldecode($pathParts[1] ?? '');
        
        switch ($method) {
            case 'PUT':
                return $this->handlePut($bucket, $objectKey, $request);
            case 'GET':
                return $this->handleGet($bucket, $objectKey, $request);
            case 'HEAD':
                return $this->handleHead($bucket, $objectKey);
            case 'DELETE':
                return $this->handleDelete($bucket, $objectKey);
            default:
                return new Response(405, [], 'Method not allowed');
        }
    }

    public function withMiddleware($middleware): TransportInterface
    {
        // For demo, just return self
        return $this;
    }

    public function withConfig(array $config): TransportInterface
    {
        return $this;
    }

    private function handlePut(string $bucket, string $objectKey, RequestInterface $request): ResponseInterface
    {
        $content = $request->getBody()->getContents();
        $contentType = $request->getHeaderLine('Content-Type');
        $storageClass = $request->getHeaderLine('x-amz-storage-class');
        
        // Extract metadata
        $metadata = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (str_starts_with(strtolower($name), 'x-amz-meta-')) {
                $metaKey = substr($name, 11);
                $metadata[$metaKey] = $values[0] ?? '';
            }
        }
        
        // Store the object
        $etag = md5($content);
        $this->storedObjects["{$bucket}:{$objectKey}"] = [
            'content' => $content,
            'contentType' => $contentType,
            'storageClass' => $storageClass,
            'metadata' => $metadata,
            'etag' => $etag,
            'lastModified' => gmdate('D, d M Y H:i:s T'),
            'contentLength' => strlen($content),
        ];
        
        echo "    ✓ Stored object with ETag: {$etag}\n";
        
        return new Response(200, ['ETag' => "\"{$etag}\""]);
    }

    private function handleGet(string $bucket, string $objectKey, RequestInterface $request): ResponseInterface
    {
        $key = "{$bucket}:{$objectKey}";
        
        if (!isset($this->storedObjects[$key])) {
            return new Response(404, [], 'Object not found');
        }
        
        $obj = $this->storedObjects[$key];
        $content = $obj['content'];
        
        // Handle range requests
        $rangeHeader = $request->getHeaderLine('Range');
        if ($rangeHeader) {
            if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
                $start = (int) $matches[1];
                $end = $matches[2] !== '' ? (int) $matches[2] : strlen($content) - 1;
                $content = substr($content, $start, $end - $start + 1);
                
                $headers = [
                    'Content-Type' => $obj['contentType'],
                    'ETag' => "\"{$obj['etag']}\"",
                    'Content-Range' => "bytes {$start}-{$end}/" . strlen($obj['content']),
                ];
                
                return new Response(206, $headers, $content);
            }
        }
        
        // Build response headers
        $headers = [
            'Content-Type' => $obj['contentType'],
            'ETag' => "\"{$obj['etag']}\"",
            'Last-Modified' => $obj['lastModified'],
            'Content-Length' => (string) $obj['contentLength'],
        ];
        
        // Add metadata headers
        foreach ($obj['metadata'] as $key => $value) {
            $headers["x-amz-meta-{$key}"] = $value;
        }
        
        echo "    ✓ Retrieved object ({$obj['contentLength']} bytes)\n";
        
        return new Response(200, $headers, $content);
    }

    private function handleHead(string $bucket, string $objectKey): ResponseInterface
    {
        $key = "{$bucket}:{$objectKey}";
        
        if (!isset($this->storedObjects[$key])) {
            return new Response(404);
        }
        
        $obj = $this->storedObjects[$key];
        
        $headers = [
            'Content-Type' => $obj['contentType'],
            'ETag' => "\"{$obj['etag']}\"",
            'Last-Modified' => $obj['lastModified'],
            'Content-Length' => (string) $obj['contentLength'],
        ];
        
        echo "    ✓ Object exists\n";
        
        return new Response(200, $headers);
    }

    private function handleDelete(string $bucket, string $objectKey): ResponseInterface
    {
        $key = "{$bucket}:{$objectKey}";
        
        if (isset($this->storedObjects[$key])) {
            unset($this->storedObjects[$key]);
            echo "    ✓ Object deleted\n";
        } else {
            echo "    ✓ Object was already deleted or didn't exist\n";
        }
        
        return new Response(204);
    }
}

// ==================== DEMO EXECUTION ====================

try {
    echo "IBM Cloud Object Storage SDK Demo\n";
    echo "==================================\n\n";

    // 1. Setup mock authentication (no real IBM API calls)
    echo "1. Setting up mock authentication...\n";
    $apiKey = new ApiKey('demo-api-key-1234567890123456789012345678901234567890123456');
    $mockAuth = new ApiKeyStrategy($apiKey, 'X-Demo-API-Key');
    echo "   ✓ Using API key authentication (mock)\n\n";
    
    // 2. Create mock transport that simulates IBM COS responses
    echo "2. Creating mock COS transport...\n";
    $mockTransport = new MockCosTransport();
    
    // Add authentication middleware
    $authenticatedTransport = new class($mockTransport, $mockAuth) implements TransportInterface {
        public function __construct(
            private TransportInterface $transport,
            private $auth
        ) {}
        
        public function send(RequestInterface $request): ResponseInterface {
            $authRequest = $this->auth->authenticate($request);
            return $this->transport->send($authRequest);
        }
        
        public function withMiddleware($middleware): TransportInterface {
            return $this->transport->withMiddleware($middleware);
        }
        
        public function withConfig(array $config): TransportInterface {
            return $this->transport->withConfig($config);
        }
    };
    
    echo "   ✓ Mock transport ready with authentication\n\n";
    
    // 3. Create Object Storage client
    echo "3. Creating Object Storage client...\n";
    $cosClient = new Client($authenticatedTransport, 'https://mock-cos.example.com');
    echo "   ✓ Client created for endpoint: {$cosClient->getServiceUrl()}\n\n";
    
    // 4. Define bucket and object
    $testBucketName = $_ENV['TEST_BUCKET_NAME'] ?? 'my-demo-bucket';
    $bucketName = BucketName::from($testBucketName);
    $objectKey = ObjectKey::from('documents/demo-file.txt');
    $content = "Hello from IBM Cloud PHP SDK!\n\nThis is a demonstration of the Object Storage service.\n\nFeatures:\n- Type-safe value objects\n- Middleware pipeline\n- Authentication integration\n- Comprehensive error handling";
    
    echo "4. Demo parameters:\n";
    echo "   ✓ Bucket: {$bucketName->toString()}\n";
    echo "   ✓ Object: {$objectKey->toString()}\n";
    echo "   ✓ Content length: " . strlen($content) . " bytes\n\n";
    
    // 5. Store object
    echo "5. Storing object...\n";
    $storeCommand = ObjectCommand::store(
        bucket: $bucketName,
        key: $objectKey,
        content: $content,
        storageClass: StorageClass::STANDARD,
        metadata: [
            'author' => 'IBM Cloud PHP SDK',
            'created-by' => 'demo-script',
            'purpose' => 'demonstration',
            'version' => '1.0'
        ],
        contentType: 'text/plain'
    );
    
    $storeResult = $cosClient->store($storeCommand);
    echo "   ✓ Object stored successfully!\n";
    echo "   ✓ ETag: {$storeResult->etag}\n";
    echo "   ✓ Storage class: {$storeResult->storageClass->value}\n\n";
    
    // 6. Check if object exists
    echo "6. Checking if object exists...\n";
    $existsQuery = ObjectQuery::for($bucketName, $objectKey);
    $exists = $cosClient->exists($existsQuery);
    echo "   " . ($exists ? "✓ Object exists" : "✗ Object not found") . "\n\n";
    
    // 7. Retrieve object with metadata
    echo "7. Retrieving object with metadata...\n";
    $retrieveQuery = ObjectQuery::withMetadata($bucketName, $objectKey);
    $retrieveResult = $cosClient->retrieve($retrieveQuery);
    
    echo "   ✓ Content type: {$retrieveResult->contentType}\n";
    echo "   ✓ Content length: {$retrieveResult->contentLength} bytes\n";
    echo "   ✓ ETag: {$retrieveResult->etag}\n";
    echo "   ✓ Last modified: {$retrieveResult->lastModified->format('Y-m-d H:i:s')} GMT\n";
    echo "   ✓ Metadata: " . json_encode($retrieveResult->metadata, JSON_PRETTY_PRINT) . "\n";
    echo "   ✓ Content preview: '" . substr($retrieveResult->getContent(), 0, 50) . "...'\n\n";
    
    // 8. Stream object (demonstrates streaming API)
    echo "8. Streaming object...\n";
    $streamQuery = ObjectQuery::for($bucketName, $objectKey);
    $stream = $cosClient->stream($streamQuery);
    $streamedContent = $stream->getContents();
    echo "   ✓ Streamed " . strlen($streamedContent) . " bytes\n";
    echo "   ✓ Content integrity: " . ($streamedContent === $content ? "✓ PASS" : "✗ FAIL") . "\n\n";
    
    // 9. Range request (partial content)
    echo "9. Retrieving partial content (bytes 0-49)...\n";
    $rangeQuery = ObjectQuery::withRange($bucketName, $objectKey, 0, 49);
    $partialResult = $cosClient->retrieve($rangeQuery);
    echo "   ✓ Partial content: '" . $partialResult->getContent() . "'\n";
    echo "   ✓ Length: {$partialResult->contentLength} bytes\n\n";
    
    // 10. Demonstrate value object features
    echo "10. Value object features:\n";
    echo "    ✓ Object directory: " . ($objectKey->getDirectory() ?? 'none') . "\n";
    echo "    ✓ Object filename: " . $objectKey->getFilename() . "\n";
    echo "    ✓ Object extension: " . ($objectKey->getExtension() ?? 'none') . "\n";
    echo "    ✓ Storage class description: " . StorageClass::STANDARD->getDescription() . "\n";
    echo "    ✓ Immediate access: " . (StorageClass::STANDARD->isImmediateAccess() ? 'Yes' : 'No') . "\n\n";
    
    // 11. Delete object
    echo "11. Deleting object...\n";
    $deleteCommand = ObjectCommand::delete($bucketName, $objectKey);
    $cosClient->delete($deleteCommand);
    echo "    ✓ Delete operation completed\n\n";
    
    // 12. Verify deletion
    echo "12. Verifying deletion...\n";
    $stillExists = $cosClient->exists($existsQuery);
    echo "    " . ($stillExists ? "✗ Object still exists" : "✓ Object successfully deleted") . "\n\n";
    
    echo "🎉 Demo completed successfully!\n\n";
    echo "This demonstration shows:\n";
    echo "✓ Type-safe value objects with validation\n";
    echo "✓ Command/Query pattern for operations\n";
    echo "✓ Authentication middleware integration\n";
    echo "✓ Streaming support for large files\n";
    echo "✓ Range requests for partial content\n";
    echo "✓ Custom metadata handling\n";
    echo "✓ Comprehensive error handling\n";
    echo "✓ Mock transport for testing\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}