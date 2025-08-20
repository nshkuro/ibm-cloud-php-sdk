<?php

require_once __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Configuration\ConfigurationBuilder;
use IBMCloud\Services\AI\Models\Requests\CompletionRequest;
use IBMCloud\Services\AI\Models\Requests\StreamRequest;
use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;
use Dotenv\Dotenv;

// Load environment variables.
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

echo "IBM WatsonX.ai Streaming Example\n";
echo "===============================\n\n";

try {
    // Build configuration with production settings.
    echo "1. Building configuration...\n";
    
    $config = ConfigurationBuilder::create()
        ->withRegion('eu-de') // Use your region.
        ->withEnvironment('development')
        ->withRetryPolicy(3, 1.0, 'exponential')
        ->withCircuitBreaker(5, 30.0)
        ->withRateLimit(5, 10, 10.0) // 5 requests/sec with burst of 10.
        ->build();
    
    echo "   ✓ Configuration built with middleware pipeline\n";
    echo "   ✓ Region: " . $config->region . "\n";
    echo "   ✓ Environment: " . $config->environment . "\n\n";

    // Create WatsonX client.
    echo "2. Creating WatsonX client...\n";
    $watsonx = $config->createWatsonXClient();
    echo "   ✓ Client created with authenticated transport\n\n";

    // Get project ID.
    $projectId = $_ENV['IBM_WATSONX_PROJECT_ID'] ?? null;
    if (!$projectId) {
        throw new Exception('IBM_WATSONX_PROJECT_ID environment variable is required');
    }

    // Test 1: Simple streaming completion.
    echo "3. Simple streaming completion...\n";
    
    $prompt = Prompt::from("Tell me a short story about a robot learning to paint. ");
    $modelId = ModelId::from("ibm/granite-13b-instruct-v2");
    
    $parameters = GenerationParameters::create()
        ->withMaxTokens(200)
        ->withTemperature(0.7)
        ->withTopP(0.9);
    
    $streamRequest = StreamRequest::create($modelId, $prompt, $projectId)
        ->withParameters($parameters)
        ->withStopReason(true)
        ->withTokenInfo(true);
    
    echo "   → Model: " . $modelId->toString() . "\n";
    echo "   → Prompt: " . substr($prompt->toString(), 0, 50) . "...\n";
    echo "   → Max tokens: " . $parameters->getMaxTokens() . "\n\n";
    
    echo "   📡 Streaming response:\n";
    echo "   " . str_repeat("-", 60) . "\n";
    
    $streamResult = $watsonx->stream($streamRequest);
    $fullText = '';
    $tokenCount = 0;
    
    foreach ($streamResult->getDetailedStream() as $chunk) {
        if (!empty($chunk['text'])) {
            echo $chunk['text'];
            $fullText .= $chunk['text'];
            flush();
        }
        
        if ($chunk['token_count'] !== null) {
            $tokenCount = $chunk['token_count'];
        }
        
        if ($chunk['finished']) {
            echo "\n   " . str_repeat("-", 60) . "\n";
            echo "   ✓ Stream completed\n";
            echo "   ✓ Stop reason: " . ($chunk['stop_reason'] ?? 'unknown') . "\n";
            echo "   ✓ Total tokens generated: $tokenCount\n\n";
            break;
        }
    }

    // Test 2: Streaming with progress tracking.
    echo "4. Streaming with progress tracking...\n";
    
    $technicalPrompt = Prompt::from("Explain the concept of machine learning in simple terms: ");
    
    $technicalRequest = StreamRequest::create($modelId, $technicalPrompt, $projectId)
        ->withParameters(
            GenerationParameters::create()
                ->withMaxTokens(150)
                ->withTemperature(0.3)
                ->withTopP(0.8)
        )
        ->withStopReason(true)
        ->withTokenInfo(true);
    
    echo "   → Technical explanation request\n";
    echo "   → Lower temperature (0.3) for more focused response\n\n";
    
    $startTime = microtime(true);
    $chunkCount = 0;
    $progressText = '';
    
    $technicalResult = $watsonx->stream($technicalRequest);
    
    foreach ($technicalResult->getDetailedStream() as $chunk) {
        $chunkCount++;
        
        if (!empty($chunk['text'])) {
            $progressText .= $chunk['text'];
            
            // Show progress every 10 chunks.
            if ($chunkCount % 10 === 0) {
                $elapsed = microtime(true) - $startTime;
                echo sprintf(
                    "   [Progress] Chunk %d | %.1fs | %d chars | %s\n",
                    $chunkCount,
                    $elapsed,
                    strlen($progressText),
                    $chunk['finished'] ? 'FINISHED' : 'STREAMING'
                );
            }
        }
        
        if ($chunk['finished']) {
            $totalTime = microtime(true) - $startTime;
            echo "\n   📊 Streaming Statistics:\n";
            echo "   → Total chunks: $chunkCount\n";
            echo "   → Total time: " . round($totalTime, 2) . "s\n";
            echo "   → Characters received: " . strlen($progressText) . "\n";
            echo "   → Average chunks/sec: " . round($chunkCount / $totalTime, 1) . "\n\n";
            break;
        }
    }

    // Test 3: Multiple concurrent streams (demonstrate rate limiting).
    echo "5. Testing rate limiting with multiple requests...\n";
    
    $shortPrompt = Prompt::from("Count from 1 to 10: ");
    
    $quickRequest = StreamRequest::create($modelId, $shortPrompt, $projectId)
        ->withParameters(
            GenerationParameters::create()
                ->withMaxTokens(50)
                ->withTemperature(0.1)
        );
    
    echo "   → Making 3 quick requests to test rate limiting...\n";
    
    for ($i = 1; $i <= 3; $i++) {
        $requestStart = microtime(true);
        
        try {
            echo "   Request $i: ";
            
            $quickResult = $watsonx->stream($quickRequest);
            $quickText = '';
            
            foreach ($quickResult->getTextStream() as $text) {
                $quickText .= $text;
            }
            
            $requestTime = microtime(true) - $requestStart;
            echo "✓ Completed in " . round($requestTime, 2) . "s\n";
            
        } catch (Exception $e) {
            $requestTime = microtime(true) - $requestStart;
            echo "❌ Failed after " . round($requestTime, 2) . "s: " . $e->getMessage() . "\n";
        }
        
        // Small delay between requests.
        if ($i < 3) {
            usleep(100000); // 0.1 second.
        }
    }
    
    echo "\n";

    // Test 4: Error handling with streaming.
    echo "6. Testing error handling...\n";
    
    try {
        // Try with invalid model to test error handling.
        $invalidModelId = ModelId::from("invalid/model-name");
        $errorPrompt = Prompt::from("This should fail");
        
        $errorRequest = StreamRequest::create($invalidModelId, $errorPrompt, $projectId)
            ->withParameters(GenerationParameters::create()->withMaxTokens(10));
        
        echo "   → Testing with invalid model: " . $invalidModelId->toString() . "\n";
        
        $errorResult = $watsonx->stream($errorRequest);
        
        foreach ($errorResult->getStream() as $chunk) {
            echo "   Unexpected success: " . json_encode($chunk) . "\n";
            break;
        }
        
    } catch (Exception $e) {
        echo "   ✓ Expected error caught: " . $e->getMessage() . "\n";
        echo "   ✓ Error handling working correctly\n\n";
    }

    // Configuration summary.
    echo "7. Configuration Summary:\n";
    $summary = $config->getSummary();
    
    foreach ($summary as $key => $value) {
        echo "   → " . ucfirst(str_replace('_', ' ', $key)) . ": ";
        echo is_array($value) ? implode(', ', $value) : $value;
        echo "\n";
    }
    
    echo "\n🎉 Streaming example completed successfully!\n\n";
    
    echo "💡 Key Features Demonstrated:\n";
    echo "   ✓ Configuration Builder with middleware pipeline\n";
    echo "   ✓ Streaming responses with real-time output\n";
    echo "   ✓ Progress tracking and statistics\n";
    echo "   ✓ Rate limiting protection\n";
    echo "   ✓ Error handling and recovery\n";
    echo "   ✓ Multiple streaming modes (text-only, detailed)\n";
    echo "   ✓ Production-ready middleware integration\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    echo "\n🔧 Troubleshooting:\n";
    echo "   1. Ensure your .env file contains:\n";
    echo "      IBM_API_KEY=your-ibm-cloud-api-key\n";
    echo "      IBM_WATSONX_PROJECT_ID=your-project-id\n";
    echo "      IBM_WATSONX_URL=https://eu-de.ml.cloud.ibm.com\n";
    echo "   2. Verify your API key has WatsonX.ai access\n";
    echo "   3. Check that your project ID is correct\n";
    echo "   4. Ensure your region matches the endpoint URL\n";
}