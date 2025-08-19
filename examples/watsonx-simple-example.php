<?php

require_once __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Services\AI\WatsonX\Client;
use IBMCloud\Services\AI\WatsonX\CompletionRequestBuilder;
use IBMCloud\Transport\HttpTransport;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

echo "WatsonX.ai Simple Test\n";
echo "=====================\n\n";

try {
    // Setup
    $apiKey = ApiKey::fromEnvironment('IBM_API_KEY');
    $projectId = $_ENV['IBM_WATSONX_PROJECT_ID'] ?? throw new Exception('Missing IBM_WATSONX_PROJECT_ID');
    
    echo "Using API Key: " . $apiKey->masked() . "\n";
    echo "Project ID: {$projectId}\n\n";

    // Create client
    $transport = new HttpTransport();
    $iamAuth = new IamStrategy($apiKey, $transport);
    $authenticatedTransport = $transport->withMiddleware(new AuthenticationMiddleware($iamAuth));
    $watsonxEndpoint = $_ENV['IBM_WATSONX_URL'] ?? 'https://us-south.ml.cloud.ibm.com';
    $watsonx = new Client($authenticatedTransport, $watsonxEndpoint);

    // Simple completion
    echo "Testing text generation...\n";
    
    $request = CompletionRequestBuilder::create()
        ->modelId('ibm/granite-13b-instruct-v2')  // Use IBM Granite model that works
        ->userMessage('Explain quantum computing in one paragraph.')
        ->projectId($projectId)
        ->focused()
        ->maxTokens(100)
        ->build();

    echo "Model: " . $request->getModelId()->toString() . "\n";
    echo "Prompt: " . $request->getPrompt()->toString() . "\n\n";

    $result = $watsonx->complete($request);

    echo "✅ Success!\n";
    echo "Generated text ({$result->getGeneratedTokenCount()} tokens):\n";
    echo $result->getText() . "\n\n";
    echo "Input tokens: " . $result->getInputTokenCount() . "\n";
    echo "Stop reason: " . $result->getStopReason() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nMake sure your .env file contains:\n";
    echo "IBM_API_KEY=your-api-key\n";
    echo "IBM_WATSONX_PROJECT_ID=your-project-id\n";
}