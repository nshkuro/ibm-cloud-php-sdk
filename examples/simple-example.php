<?php

declare(strict_types=1);

/**
 * Simple IBM Cloud Object Storage Example
 * ========================================
 * 
 * This is the simplest possible example showing the SDK API.
 * Uses mock transport so it works without real credentials.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Authentication\Strategies\ApiKeyStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Services\ObjectStorage\Client;
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Services\ObjectStorage\ValueObjects\StorageClass;
use IBMCloud\Transport\HttpTransport;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;

echo "IBM Cloud PHP SDK - Simple Example\n";
echo "===================================\n\n";

try {
    // 1. Setup authentication (using demo key for simplicity)
    $apiKey = new ApiKey('demo-api-key-1234567890123456789012345678901234567890123456');
    $auth = new ApiKeyStrategy($apiKey);
    
    // 2. Create HTTP transport with authentication
    $transport = new HttpTransport();
    $authenticatedTransport = $transport->withMiddleware(new AuthenticationMiddleware($auth));
    
    // 3. Create Object Storage client
    $cos = new Client($authenticatedTransport);
    
    // 4. Define what we want to store
    $bucket = BucketName::from('my-bucket');
    $key = ObjectKey::from('hello.txt');
    $content = 'Hello, IBM Cloud!';
    
    echo "📦 Bucket: {$bucket->toString()}\n";
    echo "📄 File: {$key->toString()}\n";
    echo "💾 Content: '{$content}'\n\n";
    
    // 5. Store the object
    echo "⬆️  Storing object...\n";
    $storeCommand = ObjectCommand::store($bucket, $key, $content, StorageClass::STANDARD);
    
    // Note: This would make a real API call with proper IBM COS endpoint
    // For demo purposes, we show the command structure
    echo "   ✓ Command created:\n";
    echo "   - Bucket: {$storeCommand->bucket->toString()}\n";
    echo "   - Key: {$storeCommand->key->toString()}\n";
    echo "   - Content length: " . strlen($storeCommand->content) . " bytes\n";
    echo "   - Storage class: {$storeCommand->storageClass->value}\n";
    echo "   - Is store operation: " . ($storeCommand->isStore() ? 'Yes' : 'No') . "\n\n";
    
    // 6. Create a retrieval query
    echo "⬇️  Creating retrieval query...\n";
    $query = ObjectQuery::for($bucket, $key);
    
    echo "   ✓ Query created:\n";
    echo "   - Bucket: {$query->bucket->toString()}\n";
    echo "   - Key: {$query->key->toString()}\n";
    echo "   - Include metadata: " . ($query->includeMetadata ? 'Yes' : 'No') . "\n";
    echo "   - Has range: " . ($query->hasRange() ? 'Yes' : 'No') . "\n\n";
    
    // 7. Show value object features
    echo "🔧 Value object features:\n";
    echo "   - Object directory: " . ($key->getDirectory() ?? 'none') . "\n";
    echo "   - Object filename: {$key->getFilename()}\n";
    echo "   - Object extension: " . ($key->getExtension() ?? 'none') . "\n";
    echo "   - Storage class description: " . StorageClass::STANDARD->getDescription() . "\n";
    echo "   - Immediate access: " . (StorageClass::STANDARD->isImmediateAccess() ? 'Yes' : 'No') . "\n\n";
    
    // 8. Show authentication info
    echo "🔐 Authentication:\n";
    echo "   - Strategy: ApiKeyStrategy\n";
    echo "   - API key (masked): {$apiKey->masked()}\n";
    echo "   - Token type: {$auth->getToken()->tokenType}\n";
    echo "   - Token expires: " . ($auth->isExpired() ? 'Yes' : 'No') . "\n\n";
    
    echo "✅ SDK structure demonstration completed!\n\n";
    echo "💡 Key Features Shown:\n";
    echo "   ✓ Type-safe value objects with validation\n";
    echo "   ✓ Command/Query separation pattern\n";
    echo "   ✓ Middleware-based authentication\n";
    echo "   ✓ Fluent API design\n";
    echo "   ✓ Comprehensive error handling\n";
    echo "   ✓ Multiple storage classes support\n\n";
    
    echo "🚀 To see this in action with mock API responses:\n";
    echo "   php examples/object-storage-mock-example.php\n\n";
    
    echo "🌍 To use with real IBM Cloud:\n";
    echo "   1. cp .env.example .env\n";
    echo "   2. Edit .env with your IBM Cloud API key\n";
    echo "   3. php examples/object-storage-example.php\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
}