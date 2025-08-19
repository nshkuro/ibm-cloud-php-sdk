<?php

declare(strict_types=1);

/**
 * IBM Cloud Object Storage - Real API Example
 * ============================================
 * 
 * ⚠️  IMPORTANT: This example requires REAL IBM Cloud credentials!
 * 
 * To use this example:
 * 1. Copy .env.example to .env: cp .env.example .env
 * 2. Edit .env and add your real IBM Cloud API key
 * 3. Replace 'my-demo-bucket' with your actual bucket name
 * 4. Ensure the bucket exists in your IBM Cloud account
 * 
 * For a working demo without real credentials, run:
 * php examples/object-storage-mock-example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Services\ObjectStorage\Client;
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Services\ObjectStorage\ValueObjects\StorageClass;
use IBMCloud\Transport\HttpTransport;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use IBMCloud\Transport\Middleware\LoggingMiddleware;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Check for required environment variable
if (!isset($_ENV['IBM_API_KEY']) && !getenv('IBM_API_KEY')) {
    echo "❌ Error: IBM_API_KEY not found in environment or .env file.\n";
    echo "\nTo run this example:\n";
    echo "1. Copy the example file: cp .env.example .env\n";
    echo "2. Edit .env and set your real IBM_API_KEY\n";
    echo "3. Run: php examples/object-storage-example.php\n";
    echo "\nAlternatively, set environment variable directly:\n";
    echo "4. export IBM_API_KEY=\"your-real-ibm-api-key\"\n";
    echo "\nFor a working demo without credentials:\n";
    echo "5. php examples/object-storage-mock-example.php\n";
    exit(1);
}

try {
    echo "IBM Cloud Object Storage Example (REAL API)\n";
    echo "============================================\n\n";

    // 1. Setup authentication
    echo "1. Setting up authentication...\n";
    $apiKey = ApiKey::fromEnvironment('IBM_API_KEY');
    $transport = new HttpTransport();
    $iamAuth = new IamStrategy($apiKey, $transport);
    echo "   ✓ Using API key: " . $apiKey->masked() . "\n";
    
    // 2. Create authenticated transport with middleware
    echo "2. Creating authenticated transport with middleware...\n";
    $authenticatedTransport = $transport
        ->withMiddleware(new AuthenticationMiddleware($iamAuth))
        ->withMiddleware(new LoggingMiddleware());
    
    // 3. Create Object Storage client
    echo "3. Creating Object Storage client...\n";
    $cosEndpoint = $_ENV['IBM_COS_ENDPOINT'] ?? 'https://s3.us-south.cloud-object-storage.appdomain.cloud';
    $cosClient = new Client($authenticatedTransport, $cosEndpoint);
    echo "   ✓ Using endpoint: {$cosEndpoint}\n";
    
    // 4. Define bucket and object
    $testBucketName = $_ENV['TEST_BUCKET_NAME'] ?? 'my-demo-bucket';
    $bucketName = BucketName::from($testBucketName);
    $objectKey = ObjectKey::from('documents/demo-file.txt');
    $content = "Hello from IBM Cloud PHP SDK!\n\nThis is a demonstration of the Object Storage service.";
    
    echo "4. Using bucket from environment: {$testBucketName}\n";
    echo "   Bucket: {$bucketName->toString()}\n";
    echo "   Object: {$objectKey->toString()}\n";
    echo "   Content length: " . strlen($content) . " bytes\n\n";
    
    // 5. Store object
    echo "5. Storing object...\n";
    $storeCommand = ObjectCommand::store(
        bucket: $bucketName,
        key: $objectKey,
        content: $content,
        storageClass: StorageClass::STANDARD,
        metadata: [
            'author' => 'IBM Cloud PHP SDK',
            'created-by' => 'example-script',
            'purpose' => 'demonstration'
        ],
        contentType: 'text/plain'
    );
    
    $storeResult = $cosClient->store($storeCommand);
    echo "   ✓ Object stored with ETag: {$storeResult->etag}\n";
    echo "   ✓ Storage class: {$storeResult->storageClass->value}\n\n";
    
    // 6. Check if object exists
    echo "6. Checking if object exists...\n";
    $existsQuery = ObjectQuery::for($bucketName, $objectKey);
    $exists = $cosClient->exists($existsQuery);
    echo "   " . ($exists ? "✓ Object exists" : "✗ Object not found") . "\n\n";
    
    // 7. Retrieve object
    echo "7. Retrieving object...\n";
    $retrieveQuery = ObjectQuery::withMetadata($bucketName, $objectKey);
    $retrieveResult = $cosClient->retrieve($retrieveQuery);
    
    echo "   ✓ Content type: {$retrieveResult->contentType}\n";
    echo "   ✓ Content length: {$retrieveResult->contentLength} bytes\n";
    echo "   ✓ ETag: {$retrieveResult->etag}\n";
    echo "   ✓ Last modified: {$retrieveResult->lastModified->format('Y-m-d H:i:s')}\n";
    echo "   ✓ Metadata: " . json_encode($retrieveResult->metadata) . "\n";
    echo "   ✓ Content preview: " . substr($retrieveResult->getContent(), 0, 50) . "...\n\n";
    
    // 8. Stream object (useful for large files)
    echo "8. Streaming object...\n";
    $streamQuery = ObjectQuery::for($bucketName, $objectKey);
    $stream = $cosClient->stream($streamQuery);
    $streamedContent = $stream->getContents();
    echo "   ✓ Streamed " . strlen($streamedContent) . " bytes\n";
    echo "   ✓ Content matches: " . ($streamedContent === $content ? "Yes" : "No") . "\n\n";
    
    // 9. Partial content retrieval (range request)
    echo "9. Retrieving partial content (first 20 bytes)...\n";
    $rangeQuery = ObjectQuery::withRange($bucketName, $objectKey, 0, 19);
    $partialResult = $cosClient->retrieve($rangeQuery);
    echo "   ✓ Partial content: '" . $partialResult->getContent() . "'\n";
    echo "   ✓ Length: {$partialResult->contentLength} bytes\n\n";
    
    // 10. Delete object
    echo "10. Deleting object...\n";
    $deleteCommand = ObjectCommand::delete($bucketName, $objectKey);
    $cosClient->delete($deleteCommand);
    echo "    ✓ Object deleted\n\n";
    
    // 11. Verify deletion
    echo "11. Verifying deletion...\n";
    $stillExists = $cosClient->exists($existsQuery);
    echo "    " . ($stillExists ? "✗ Object still exists" : "✓ Object successfully deleted") . "\n\n";
    
    echo "Example completed successfully! 🎉\n";
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}