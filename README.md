# IBM Cloud PHP SDK

> ⚠️ **Disclaimer**: This SDK is entirely AI-generated and is not an official IBM product. Use at your own risk and always verify the generated code for production use.

A modern, type-safe PHP SDK for IBM Cloud Services with enterprise-grade features.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

## Overview

The IBM Cloud PHP SDK provides a comprehensive, modern interface to IBM Cloud services with a focus on:

- **Type Safety**: Full PHP 8.1+ type declarations with readonly properties and enums
- **Enterprise Architecture**: Contract-oriented design with SOLID principles
- **Performance**: Middleware pipeline with connection pooling and circuit breaker patterns
- **Developer Experience**: Fluent builders, comprehensive error handling, and extensive logging
- **Extensibility**: Plugin-based architecture with minimal dependencies

## Phase 1 Features ✅

### Core Infrastructure
- [x] **Authentication System**: IAM, API Key, and Token strategies with automatic refresh
- [x] **HTTP Transport**: Guzzle-based transport with middleware pipeline support
- [x] **Configuration Management**: Fluent builder with environment and file-based providers

### Middleware Pipeline
- [x] **Retry Middleware**: Exponential, linear, and fixed delay strategies with jitter
- [x] **Circuit Breaker**: Threshold-based protection with half-open state transitions
- [x] **Rate Limiting**: Token bucket algorithm with configurable burst capacity
- [x] **Authentication**: Automatic token refresh and request signing
- [x] **Logging**: Structured logging with configurable levels and request tracking

### IBM Cloud Services
- [x] **WatsonX.ai**: Foundation models with streaming responses and text extraction
- [x] **WatsonX Chat API**: Multi-turn conversations with tool/function calling support
- [x] **Object Storage**: Full CRUD operations with streaming and batch support
- [x] **Text Extraction**: Document processing via WatsonX.ai with results management

### Developer Tools
- [x] **Comprehensive Examples**: Real-world usage patterns and integration demos
- [x] **Unit Tests**: Core middleware and transport layer testing
- [x] **Type Definitions**: Full IntelliSense support with detailed PHPDoc

## Quick Start

### Installation

```bash
composer require nshkuro/ibm-cloud-php-sdk
```

### Basic Configuration

```php
<?php

use IBMCloud\Configuration\ConfigurationBuilder;

// Load environment variables
require_once 'vendor/autoload.php';

// Create configuration with production settings
$config = ConfigurationBuilder::create()
    ->withRegion('eu-de')
    ->withEnvironment('production')
    ->withRetryPolicy(3, 1.0, 'exponential')
    ->withCircuitBreaker(5, 60.0)
    ->withRateLimit(10, 20, 30.0)
    ->build();

// Create service clients
$watsonx = $config->createWatsonXClient();
$cos = $config->createCOSClient();
```

### Environment Variables

Create a `.env` file in your project root:

```bash
# Required
IBM_API_KEY=your-ibm-cloud-api-key
IBM_WATSONX_PROJECT_ID=your-watsonx-project-id

# Optional - defaults to appropriate regions
IBM_WATSONX_URL=https://eu-de.ml.cloud.ibm.com
IBM_REGION=eu-de

# For text extraction
IBM_DOCUMENT_CONNECTION_ID=your-connection-id
IBM_RESULTS_CONNECTION_ID=your-results-connection-id

# For Object Storage
TEST_BUCKET_NAME=your-bucket-name
IBM_COS_SERVICE_INSTANCE_ID=your-service-instance-id
```

## Usage Examples

### WatsonX Foundation Models

```php
use IBMCloud\Services\AI\Models\Requests\CompletionRequest;
use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;

$modelId = ModelId::from('ibm/granite-13b-instruct-v2');
$prompt = Prompt::from('Explain machine learning in simple terms: ');

$parameters = GenerationParameters::create()
    ->withMaxTokens(200)
    ->withTemperature(0.7)
    ->withTopP(0.9);

$request = CompletionRequest::create($modelId, $prompt, $projectId)
    ->withParameters($parameters);

$completion = $watsonx->complete($request);
echo $completion->getResults()[0]['generated_text'];
```

### Chat API - Multi-turn Conversations

> **Note**: The Chat API requires models that support chat format. Currently tested with `mistralai/mistral-large`. Some models may require deployment with prompt templates.

```php
use IBMCloud\Services\AI\Models\Requests\ChatRequest;
use IBMCloud\Services\AI\ValueObjects\ChatMessage;
use IBMCloud\Services\AI\ValueObjects\ModelId;

// Create a conversation
$messages = [
    ChatMessage::user("What is the capital of France?"),
];

$request = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    $messages
)->withProjectId($projectId);

$response = $watsonx->chat($request);
echo $response->getContent(); // "The capital of France is Paris..."

// Continue the conversation
$messages[] = ChatMessage::assistant($response->getContent());
$messages[] = ChatMessage::user("What is the population?");

$request2 = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    $messages
)->withProjectId($projectId);

$response2 = $watsonx->chat($request2);
echo $response2->getContent(); // "The population of Paris is approximately 2.1 million..."
```

### Chat API - Tool/Function Calling

```php
use IBMCloud\Services\AI\Models\ChatTool;
use IBMCloud\Services\AI\Models\ChatToolChoice;

// Define available tools
$tools = [
    ChatTool::function(
        'get_weather',
        'Get the current weather for a location',
        ChatTool::createParameterSchema([
            'location' => ChatTool::stringParam('City and country'),
            'unit' => ChatTool::stringParam('Temperature unit', ['celsius', 'fahrenheit']),
        ], ['location'])
    ),
];

// Create request with tools
$request = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    [ChatMessage::user("What's the weather in Paris?")]
)
->withProjectId($projectId)
->withTools($tools, ChatToolChoice::auto());

$result = $watsonx->chat($request);

// Check if model wants to call a tool
if ($result->hasToolCalls()) {
    foreach ($result->getAllToolCalls() as $toolCall) {
        echo "Function: " . $toolCall->getFunctionName() . "\n";
        echo "Arguments: " . $toolCall->getArguments() . "\n";
        // Execute your function here and send results back
    }
}
```

### Streaming Responses

```php
use IBMCloud\Services\AI\Models\Requests\StreamRequest;

$streamRequest = StreamRequest::create($modelId, $prompt, $projectId)
    ->withParameters($parameters)
    ->withStopReason(true);

$streamResult = $watsonx->stream($streamRequest);

foreach ($streamResult->getTextStream() as $text) {
    echo $text;
    flush();
}
```

### Object Storage Operations

```php
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;

$storeCommand = ObjectCommand::store(
    bucket: BucketName::from('my-bucket'),
    key: ObjectKey::from('documents/example.txt'),
    content: 'Hello, IBM Cloud Object Storage!',
    contentType: 'text/plain'
);

$result = $cos->store($storeCommand);
echo "File stored with ETag: " . $result->getETag();
```

### Text Extraction

```php
use IBMCloud\Services\AI\Models\Requests\TextExtractionRequest;
use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionDataReference;
use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionParameters;

$docRef = TextExtractionDataReference::connectionAsset(
    connectionId: $_ENV['IBM_DOCUMENT_CONNECTION_ID'],
    fileName: 'documents/sample.pdf'
);

$resultsRef = TextExtractionDataReference::connectionAsset(
    connectionId: $_ENV['IBM_RESULTS_CONNECTION_ID'],
    fileName: 'results/extraction_' . time() . '/'
);

$extractionRequest = TextExtractionRequest::create($docRef, $resultsRef)
    ->withProjectId($projectId)
    ->withParameters(TextExtractionParameters::basic());

$extractionResult = $watsonx->extractText($extractionRequest);
echo "Extraction job created: " . $extractionResult->getId();
```

## Middleware Configuration

### Retry Policies

```php
$config = ConfigurationBuilder::create()
    ->withRetryPolicy(
        maxAttempts: 3,
        baseDelay: 1.0,
        strategy: 'exponential' // 'linear', 'fixed'
    );
```

### Circuit Breaker

```php
$config = ConfigurationBuilder::create()
    ->withCircuitBreaker(
        failureThreshold: 5,    // Open after 5 failures
        timeout: 60.0          // Try again after 60 seconds
    );
```

### Rate Limiting

```php
$config = ConfigurationBuilder::create()
    ->withRateLimit(
        requestsPerSecond: 10,  // Base rate
        burstCapacity: 20,      // Allow bursts up to 20
        maxWaitTime: 30.0       // Max wait for token
    );
```

## Advanced Configuration

### Custom Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('ibm-cloud-sdk');
$logger->pushHandler(new StreamHandler('app.log', Logger::DEBUG));

$config = ConfigurationBuilder::create()
    ->withLogger($logger)
    ->build();
```

### Service-Specific Configuration

```php
$config = ConfigurationBuilder::create()
    ->withWatsonX([
        'url' => 'https://custom.watsonx.endpoint.com',
        'project_id' => 'custom-project-id'
    ])
    ->withObjectStorage([
        'endpoint' => 'https://custom.cos.endpoint.com',
        'service_instance_id' => 'custom-instance-id'
    ])
    ->build();
```

## Architecture

### Core Components

```
├── Authentication/          # IAM, API Key, Token strategies
├── Configuration/           # Builder pattern with provider chain
├── Transport/              # HTTP client with middleware pipeline
│   ├── Middleware/         # Retry, Circuit Breaker, Rate Limiting
│   └── Pool/              # Connection pooling (planned)
├── Services/              # IBM Cloud service implementations
│   ├── AI/WatsonX/        # Foundation models and text extraction
│   └── ObjectStorage/     # Cloud Object Storage operations
├── Contracts/             # Interfaces for all major components
└── Exceptions/            # Hierarchical exception system
```

### Middleware Pipeline

The SDK uses a middleware pipeline for cross-cutting concerns:

1. **Authentication**: Automatic token refresh and request signing
2. **Logging**: Request/response logging with configurable detail levels
3. **Retry**: Configurable retry strategies with backoff and jitter
4. **Circuit Breaker**: Prevent cascading failures with threshold-based protection
5. **Rate Limiting**: Token bucket algorithm with burst capacity

## Error Handling

The SDK provides comprehensive error handling with structured exceptions:

```php
use IBMCloud\Exceptions\Transport\NetworkException;
use IBMCloud\Exceptions\Service\ServiceException;
use IBMCloud\Exceptions\Authentication\AuthenticationException;

try {
    $result = $watsonx->complete($request);
} catch (AuthenticationException $e) {
    // Handle authentication failures
    echo "Auth error: " . $e->getMessage();
} catch (NetworkException $e) {
    // Handle network issues (retryable)
    echo "Network error: " . $e->getMessage();
} catch (ServiceException $e) {
    // Handle service-specific errors
    echo "Service error: " . $e->getMessage();
}
```

## Examples

The `examples/` directory contains comprehensive usage examples demonstrating real-world implementations.

### Prerequisites for Examples

1. **Setup Environment Variables**:
```bash
cd examples
cp .env.example .env
# Edit .env and add your IBM Cloud credentials
```

2. **Required Environment Variables**:
```bash
# Core Authentication
IBM_API_KEY=your-ibm-cloud-api-key

# WatsonX Configuration
IBM_WATSONX_PROJECT_ID=your-project-id
IBM_WATSONX_URL=https://eu-de.ml.cloud.ibm.com

# Object Storage
TEST_BUCKET_NAME=your-bucket-name
IBM_COS_SERVICE_INSTANCE_ID=your-instance-id

# Text Extraction
IBM_DOCUMENT_CONNECTION_ID=your-doc-connection
IBM_RESULTS_CONNECTION_ID=your-results-connection
```

### Available Examples

#### Core Examples

- **`simple-example.php`**: Quick start demonstration of basic SDK usage
- **`complete-pipeline-example.php`**: Full middleware pipeline with all features enabled

#### WatsonX AI Examples

- **`watsonx-example.php`**: Foundation models with text generation
  - Shows model selection, parameter configuration
  - Demonstrates completion requests with different prompts

- **`watsonx-chat-example.php`**: Chat API with multi-turn conversations
  - Multi-turn dialogue management
  - Tool/function calling demonstrations
  - JSON response mode
  - Context injection
  - Token usage tracking

- **`watsonx-streaming-example.php`**: Real-time streaming responses
  - Token-by-token streaming for interactive applications
  - Progress tracking and partial response handling

- **`watsonx-simple-example.php`**: Minimal WatsonX setup
  - Simplest possible implementation
  - Good starting point for new users

- **`watsonx-text-extraction-example.php`**: Document processing
  - PDF and document text extraction
  - Results management and retrieval
  
- **`watsonx-text-extraction-simple.php`**: Basic text extraction
  - Simplified document processing workflow
  
- **`watsonx-excel-test.php`**: Excel file processing
  - Extract data from Excel spreadsheets
  - Structured data extraction examples

- **`watsonx-analyze-results.php`**: Process extraction results
  - Parse and analyze extracted text
  - Handle structured extraction output

- **`watsonx-real-test.php`**: Production-ready implementation
  - Real-world error handling
  - Retry strategies and resilience patterns

#### Object Storage Examples

- **`object-storage-example.php`**: Real IBM COS integration
  - Full CRUD operations (Create, Read, Update, Delete)
  - Streaming support for large files
  - Custom metadata handling
  
- **`object-storage-mock-example.php`**: Mock implementation for testing
  - Works without real credentials
  - Perfect for development and testing
  - Demonstrates all SDK features

### Running Examples

```bash
# Navigate to examples directory
cd examples

# Run a simple example (requires valid credentials)
php simple-example.php

# Run mock example (no credentials needed)
php object-storage-mock-example.php

# Run WatsonX streaming demo
php watsonx-streaming-example.php

# Process documents with text extraction
php watsonx-text-extraction-example.php
```

### Example Output

**WatsonX Completion Example**:
```
Initializing WatsonX client...
✓ Client configured for region: eu-de

Sending completion request...
Model: ibm/granite-13b-instruct-v2
Prompt: "Explain quantum computing in simple terms"

Response:
"Quantum computing uses quantum bits (qubits) that can exist in 
multiple states simultaneously, unlike classical bits that are 
either 0 or 1. This allows quantum computers to process many 
calculations at once..."

Tokens used: 150
Generation time: 1.2s
```

**Object Storage Example**:
```
IBM Cloud Object Storage Demo
=============================

1. Authenticating with IBM Cloud...
   ✓ Token obtained successfully

2. Creating COS client...
   ✓ Connected to: s3.us-south.cloud-object-storage.appdomain.cloud

3. Uploading file...
   ✓ Uploaded: documents/example.txt (2.5 MB)
   ETag: "d41d8cd98f00b204e9800998ecf8427e"

4. Listing objects...
   ✓ Found 3 objects in bucket
   - documents/example.txt (2.5 MB)
   - images/logo.png (45 KB)
   - data/report.pdf (1.2 MB)
```

## Development

### Requirements

- PHP 8.1+ (8.2+ recommended)
- Composer
- IBM Cloud API key

### Testing

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run with coverage
composer test-coverage

# Code style check
composer cs-check

# Static analysis
composer analyse
```

### Contributing

1. Follow PSR-12 coding standards
2. Add comprehensive tests for new features
3. Update documentation for public APIs
4. Use type hints and readonly properties where applicable

## License

Licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Support

- **Documentation**: [IBM Cloud Docs](https://cloud.ibm.com/docs)
- **API Reference**: [Context7 Resources](https://context7.com/ibm-cloud-docs)
- **Issues**: [GitHub Issues](https://github.com/nshkuro/ibm-cloud-php-sdk/issues)
- **Community**: [IBM Developer Community](https://developer.ibm.com/community/)

## Author

**Nikolay Shkuro** - [nikolay@shkuro.net](mailto:nikolay@shkuro.net)

---

**Built with ❤️ and AI assistance for the IBM Cloud community**